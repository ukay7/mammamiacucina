<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AccountingLedger
{
    public const HEADERS = ['reference', 'posted_on', 'classification', 'account', 'amount_cad', 'cash_flow', 'supplier_id', 'document', 'due_on', 'memo'];

    public const LABELS = [
        'cash' => 'Cash and bank', 'asset' => 'Other assets', 'gst_itc' => 'Approved input tax credits',
        'liability' => 'Other liabilities', 'payable' => 'Accounts payable', 'gst_payable' => 'GST/HST payable', 'gst_collected' => 'GST/HST collected',
        'equity' => 'Equity', 'revenue' => 'Revenue', 'cogs' => 'Cost of goods sold', 'expense' => 'Expenses',
    ];

    public function import(string $path, User $user): int
    {
        $file = fopen($path, 'r');
        $journals = [];
        $number = 1;
        try {
            $header = fgetcsv($file, escape: '');
            if ($header) $header[0] = ltrim($header[0], "\xEF\xBB\xBF");
            if ($header !== self::HEADERS) {
                $this->fail('The file header must match the CSV template.');
            }
            $suppliers = DB::table('suppliers')->pluck('id')->flip();
            while (($values = fgetcsv($file, escape: '')) !== false) {
                $number++;
                if ($values === [null]) continue;
                if (count($values) !== count(self::HEADERS) || $number > 10001) {
                    $this->fail('Invalid columns or more than 10,000 rows at line '.$number.'.');
                }
                $row = array_combine(self::HEADERS, array_map(fn ($value) => trim($value) === '' ? null : trim($value), $values));
                $validator = Validator::make($row, [
                    'reference' => 'required|string|max:100', 'posted_on' => 'required|date_format:Y-m-d',
                    'classification' => ['required', Rule::in(array_keys(self::LABELS))],
                    'account' => 'required|string|max:120',
                    'amount_cad' => ['required', 'regex:/^-?\d{1,10}(\.\d{1,2})?$/D'],
                    'cash_flow' => 'required_if:classification,cash|prohibited_unless:classification,cash|nullable|in:operating,investing,financing,transfer,opening',
                    'supplier_id' => 'required_if:classification,payable|nullable|integer|min:1',
                    'document' => 'required_if:classification,payable,gst_itc|nullable|string|max:120',
                    'due_on' => 'nullable|date_format:Y-m-d', 'memo' => 'nullable|string|max:255',
                ]);
                if ($validator->fails()) $this->fail('Line '.$number.': '.$validator->errors()->first());
                if ($row['supplier_id'] !== null && ! $suppliers->has((int) $row['supplier_id'])) {
                    $this->fail('Line '.$number.': supplier ID was not found.');
                }
                $reference = mb_strtoupper($row['reference']);
                $date = $row['posted_on'];
                $amount = self::cents($row['amount_cad']);
                if (! $amount) $this->fail('Line '.$number.': amount must be nonzero.');
                $journals[$reference] ??= ['date' => $date, 'entries' => [], 'balance' => 0];
                if ($journals[$reference]['date'] !== $date) $this->fail('All lines in journal '.$reference.' must have the same posting date.');
                unset($row['reference'], $row['posted_on'], $row['amount_cad']);
                $journals[$reference]['entries'][] = $row + ['amount_cents' => $amount];
                $journals[$reference]['balance'] += $amount;
            }
        } finally {
            fclose($file);
        }
        if (! $journals) $this->fail('The CSV does not contain any journal entries.');
        foreach ($journals as $reference => $journal) {
            if ($journal['balance'] !== 0) $this->fail('Journal '.$reference.' does not balance.');
        }

        try {
            return DB::transaction(function () use ($journals, $user) {
                foreach (array_chunk(array_keys($journals), 200) as $references) {
                    if ($existing = DB::table('accounting_journals')->whereIn('reference', $references)->value('reference')) {
                        $this->fail('Journal '.$existing.' is already posted. Use a new reference for an adjustment.');
                    }
                }
                foreach ($journals as $reference => $journal) {
                    $id = DB::table('accounting_journals')->insertGetId([
                        'reference' => $reference, 'posted_on' => $journal['date'], 'recorded_by' => $user->id, 'created_at' => now(),
                    ]);
                    foreach (array_chunk($journal['entries'], 200) as $entries) {
                        DB::table('accounting_entries')->insert(array_map(fn ($entry) => $entry + ['journal_id' => $id], $entries));
                    }
                }

                return count($journals);
            }, 3);
        } catch (UniqueConstraintViolationException) {
            $this->fail('A journal with the same reference was already posted. Use a new reference for an adjustment.');
        }
    }

    public function entries(array $filters, bool $period = true)
    {
        return DB::table('accounting_entries as e')->join('accounting_journals as j', 'j.id', '=', 'e.journal_id')
            ->leftJoin('suppliers as s', 's.id', '=', 'e.supplier_id')
            ->where('j.posted_on', '<=', $filters['to'])
            ->when($period, fn ($query) => $query->where('j.posted_on', '>=', $filters['from']))
            ->when($filters['supplier'] ?? null, fn ($query, $supplier) => $query->where('e.supplier_id', $supplier));
    }

    public static function cents(string $value): int
    {
        [$whole, $fraction] = array_pad(explode('.', ltrim($value, '-'), 2), 2, '');
        return ((int) $whole * 100 + (int) str_pad($fraction, 2, '0')) * (str_starts_with($value, '-') ? -1 : 1);
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['ledger' => $message]);
    }
}
