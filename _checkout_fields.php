<?php
// _checkout_fields.php — included inside forms in cart.php
// Renders order type, time slot, and live delivery estimate.
// No standalone output — this file is purely a template partial.
?>
<div class="mb-3 mt-2">
    <label class="form-label fw-bold">Order Type</label>
    <div class="d-flex gap-2">
        <label class="order-type-card flex-fill text-center">
            <input class="d-none" type="radio" name="orderType" value="pickup" checked>
            <div class="fw-bold">🏪 Pickup</div>
            <small class="text-muted">Same-day</small>
        </label>
        <label class="order-type-card flex-fill text-center">
            <input class="d-none" type="radio" name="orderType" value="delivery">
            <div class="fw-bold">🚚 Delivery</div>
            <small class="text-muted">1–3 days</small>
        </label>
    </div>
</div>

<div class="mb-3">
    <label class="form-label fw-bold">Preferred Time Slot</label>
    <div class="d-flex flex-column gap-2">
        <label class="time-slot-card d-flex align-items-center gap-2">
            <input type="radio" name="timeSlot" value="morning" checked>
            <div>
                <div class="fw-semibold">🌅 Morning</div>
                <small class="text-muted">8:00 AM – 12:00 PM</small>
            </div>
        </label>
        <label class="time-slot-card d-flex align-items-center gap-2">
            <input type="radio" name="timeSlot" value="afternoon">
            <div>
                <div class="fw-semibold">☀️ Afternoon</div>
                <small class="text-muted">1:00 PM – 5:00 PM</small>
            </div>
        </label>
        <label class="time-slot-card d-flex align-items-center gap-2">
            <input type="radio" name="timeSlot" value="evening">
            <div>
                <div class="fw-semibold">🌆 Evening</div>
                <small class="text-muted">6:00 PM – 8:00 PM</small>
            </div>
        </label>
    </div>
</div>

<div class="delivery-estimate-box mt-2">
    <div class="d-flex align-items-center gap-2">
        <span style="font-size:1.3rem">📅</span>
        <div>
            <div class="fw-bold text-success small">Estimated Delivery / Pickup</div>
            <div class="estimate-preview small text-muted">Today (Pickup) – 8:00 AM – 12:00 PM</div>
        </div>
    </div>
</div>
