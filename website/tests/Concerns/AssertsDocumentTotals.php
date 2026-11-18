<?php

namespace Tests\Concerns;

use Illuminate\Testing\TestResponse;

trait AssertsDocumentTotals
{
    /** Check each amount belongs to its label, independent of markup/spacing between cells. */
    private function assertDocumentTotals(TestResponse $response, array $expected): void
    {
        $dom = new \DOMDocument;
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        $actual = [];
        foreach ($xpath->query('//table[contains(concat(" ", normalize-space(@class), " "), " doc-totals ")]//tr') as $row) {
            $cells = $xpath->query('./td', $row);
            if ($cells->length === 2) $actual[trim($cells->item(0)->textContent)] = trim($cells->item(1)->textContent);
        }
        foreach ($expected as $label => $amount) {
            $this->assertArrayHasKey($label, $actual);
            $this->assertSame($amount, $actual[$label], $label);
        }
    }
}
