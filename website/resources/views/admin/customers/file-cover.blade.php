<section class="panel panel-content" id="customer-file-cover" aria-labelledby="customer-file-cover-title">
<h3 id="customer-file-cover-title">Customer file cover</h3>
<form method="get" action="{{ route('admin.customers.file-cover', $customer) }}" target="_blank" rel="noopener">
<div class="form-row">
<div class="form-group col-6"><label for="customer-cover-year">Year</label><input class="form-control" id="customer-cover-year" name="year" type="text" value="{{ now()->format('Y') }}" maxlength="20" required autocomplete="off"></div>
<div class="form-group col-6"><label for="customer-cover-volume">Volume</label><input class="form-control" id="customer-cover-volume" name="volume" type="text" value="01" maxlength="20" required autocomplete="off"></div>
</div>
<button class="btn btn-primary btn-block" type="submit"><i class="fas fa-print mr-1" aria-hidden="true"></i>Print / PDF</button>
<small class="d-block mt-2 text-muted">Opens an A4 PDF in a new tab. Year and Volume are used for this print only.</small>
</form>
</section>
