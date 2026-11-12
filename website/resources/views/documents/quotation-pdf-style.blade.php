{{-- Dompdf needs table layout for the existing quotation's grid/flex panels. --}}
<style>
.quote-client{display:table;width:100%;table-layout:fixed;padding:0}
.quote-client>div{display:table-cell;width:50%;padding:10px;vertical-align:top}
.quote-bottom{display:table;width:100%;table-layout:fixed}
.quote-terms{display:table-cell;width:60%;padding-right:20px;vertical-align:top}
.quote-summary{display:table-cell;width:40%;vertical-align:top;font-family:'DejaVu Sans',sans-serif}
.quote-summary>div{display:table;width:100%;table-layout:fixed;padding:0}
.quote-summary>div>span,.quote-summary>div>strong{display:table-cell;vertical-align:top;padding:4px 6px}
.quote-summary>div>span{width:70%}.quote-summary>div>strong{width:30%;text-align:right}
.quote-summary .quote-grand{padding:0}
</style>
