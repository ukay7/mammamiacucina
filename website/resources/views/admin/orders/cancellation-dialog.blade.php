<dialog id="order-cancellation-dialog" aria-labelledby="order-cancellation-title" aria-describedby="order-cancellation-description">
    <h2 id="order-cancellation-title">Return items to inventory?</h2>
    <p id="order-cancellation-description">When cancelling <strong data-cancellation-order></strong>, do you want to return all its deducted item quantities to inventory?</p>
    <p class="order-cancellation-hint">Choose Yes only for items that should be available in stock again. Your choice will be recorded in order history.</p>
    <div class="order-cancellation-actions">
        <button type="button" class="btn btn-primary" data-cancellation-choice="1">Yes, return to inventory</button>
        <button type="button" class="btn btn-outline-primary" data-cancellation-choice="0">No, keep stock unchanged</button>
        <button type="button" class="btn btn-outline-secondary" data-cancellation-back>Go back</button>
    </div>
</dialog>
