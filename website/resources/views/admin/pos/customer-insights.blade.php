<div id="pos-customer-insights" class="pos-customer-insights" data-url="{{ route('admin.pos.customer-summary') }}" hidden>
    <p id="pos-insights-status" class="pos-insights-status" role="status"></p>
    <div id="pos-insights-content" hidden>
        <div class="pos-insights-overview">
            <span id="pos-customer-tier" class="pos-tier"></span>
            <div class="pos-available-credit"><span>Available credit</span><strong id="pos-available-credit"></strong></div>
            <a id="pos-open-profile" class="btn btn-outline-primary btn-sm" target="_blank" rel="noopener" hidden>View profile <i class="fas fa-external-link-alt" aria-hidden="true"></i></a>
        </div>
        <button id="pos-open-dues" class="pos-dues-warning" type="button" hidden>
            <i class="fas fa-lightbulb" aria-hidden="true"></i>
            <span id="pos-dues-warning-text"></span>
            <span class="pos-dues-action">View dues</span>
        </button>
    </div>
</div>
