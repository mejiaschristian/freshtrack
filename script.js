function loadEditModal(item) {
    const el = (id) => document.getElementById(id);

    if (el("itemID")) el("itemID").value = item.itemID;
    if (el("edit_itemName")) el("edit_itemName").value = item.itemName;
    if (el("edit_itemDescription"))
        el("edit_itemDescription").value = item.itemDescription;
    if (el("edit_categoryID")) el("edit_categoryID").value = item.categoryID;
    if (el("edit_itemPrice")) el("edit_itemPrice").value = item.itemPrice;
    // quantity and expiry are managed via batches; only set if field exists
    if (el("edit_itemQuantity"))
        el("edit_itemQuantity").value = item.itemQuantity;
    if (el("edit_itemUnit")) el("edit_itemUnit").value = item.itemUnit;
    if (el("edit_itemExpiryDate"))
        el("edit_itemExpiryDate").value = item.itemExpiryDate;
    if (el("edit_existingImage"))
        el("edit_existingImage").value = item.itemImage ?? ""; // preserve existing image
    if (el("current_srp")) el("current_srp").value = 0;
    if (el("srpDisplay")) el("srpDisplay").textContent = "";

    const modalEl = document.getElementById("editItemModal");
    if (modalEl) new bootstrap.Modal(modalEl).show();
}

function setDeleteItemID(itemID, itemName) {
    document.getElementById("deleteItemID").value = itemID;
    document.getElementById("deleteItemName").value = itemName;
    document.getElementById("deleteItemNameDisplay").textContent = itemName;
}

function validateBatchForm() {
    const qty = document.getElementById("batchQuantityInput").value;
    const expiry = document.getElementById("batchExpiryDateInput").value;
    const harvest = document.getElementById("batchHarvestDateInput").value;

    if (!qty || parseInt(qty, 10) < 1) {
        alert("Please enter a valid quantity (minimum 1).");
        return false;
    }
    if (!harvest) {
        alert("Please select a harvest / received date.");
        return false;
    }
    if (!expiry) {
        alert("Please select an expiry date.");
        return false;
    }
    if (expiry <= harvest) {
        alert("Expiry date must be after the harvest date.");
        return false;
    }
    return true;
}

function openAddBatch(itemID, itemName) {
    document.getElementById("batch_itemID").value = itemID;
    document.getElementById("batch_itemName_display").textContent =
        "Adding batch for: " + itemName;
    document.getElementById("batchQuantityInput").value = "";
    document.getElementById("batchExpiryDateInput").value = "";
    document.getElementById("batchHarvestDateInput").value = new Date()
        .toISOString()
        .slice(0, 10);
    new bootstrap.Modal(document.getElementById("addBatchModal")).show();
}

let pendingDeleteForm = null; // Tracks which form row is awaiting removal approval

function openBatchList(itemID, batches, itemName) {
    const batchBody = document.getElementById("batchListBody");
    const historyBody = document.getElementById("batchHistoryBody");
    const batchLabel = document.getElementById("batchListLabel");

    if (batchLabel) {
        batchLabel.textContent = "Batches — " + itemName;
    }
    if (batchBody) {
        batchBody.innerHTML = "";
    }
    if (historyBody) {
        historyBody.innerHTML = "";
    }

    if (!batches || batches.length === 0) {
        if (batchBody) {
            batchBody.innerHTML =
                '<p class="text-muted p-3">No active batches found for this product.</p>';
        }
        if (historyBody) {
            historyBody.innerHTML =
                '<p class="text-muted p-3">No historical batches found.</p>';
        }
        new bootstrap.Modal(document.getElementById("batchListModal")).show();
        return;
    }

    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const activeBatches = batches.filter(
        (b) =>
            (b.batchStatus === "active" || b.batchStatus === "") &&
            Number(b.quantity) > 0,
    );
    const historyBatches = batches.filter(
        (b) => b.batchStatus === "archived" || Number(b.quantity) <= 0,
    );

    if (activeBatches.length === 0) {
        if (batchBody) {
            batchBody.innerHTML =
                '<div class="alert alert-info text-center my-2 py-3">No active fresh stock batches found for this product.</div>';
        }
    } else {
        let activeHtml = `
            <div class="alert alert-info small p-2 mb-2">
                <strong>FIFO Order:</strong> Batches are sorted oldest-harvest-first. Stock is consumed from Row 1 downward.
            </div>
            <table class="table table-sm table-striped align-middle small">
                <thead class="table-secondary">
                    <tr>
                        <th>#</th>
                        <th>Batch Code</th>
                        <th>Harvest Date</th>
                        <th>Expiry Date</th>
                        <th>Remaining</th>
                        <th>Initial</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>`;

        let fifoFirst = true;
        activeBatches.forEach((b, i) => {
            const exp = new Date(b.expiryDate);
            const diffDays = Math.round((exp - today) / 86400000);
            let expiryClass = "";
            let expiryLabel = b.expiryDate;

            if (diffDays <= 0) {
                expiryClass = "table-danger";
                expiryLabel += " ⚠ EXPIRED";
            } else if (diffDays <= 3) {
                expiryClass = "table-danger";
                expiryLabel += " (" + diffDays + "d left)";
            } else if (diffDays <= 7) {
                expiryClass = "table-warning";
                expiryLabel += " (" + diffDays + "d left)";
            }

            const fifoBadge = fifoFirst
                ? '<span class="badge bg-success ms-1">FIFO Next</span>'
                : "";
            fifoFirst = false;

            activeHtml += `
                <tr class="${expiryClass}">
                    <td>${i + 1}</td>
                    <td><code>${b.batchCode}</code> ${fifoBadge}</td>
                    <td>${b.harvestDate}</td>
                    <td>${expiryLabel}</td>
                    <td><strong>${b.quantity}</strong></td>
                    <td>${b.initialQty}</td>
                    <td>
                        <form method="POST" onsubmit="event.preventDefault(); pendingDeleteForm = this; new bootstrap.Modal(document.getElementById('deleteBatchConfirmModal')).show();">
                            <input type="hidden" name="action" value="delete_batch">
                            <input type="hidden" name="batchID" value="${b.batchID}">
                            <input type="hidden" name="itemID" value="${b.itemID}">
                            <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2">Remove</button>
                        </form>
                    </td>
                </tr>`;
        });

        activeHtml += "</tbody></table>";
        if (batchBody) {
            batchBody.innerHTML = activeHtml;
        }
    }

    if (historyBatches.length === 0) {
        if (historyBody) {
            historyBody.innerHTML =
                '<div class="alert alert-light text-muted text-center my-2 py-3">No historical or depleted batches recorded.</div>';
        }
    } else {
        let historyHtml = `
            <table class="table table-sm table-hover align-middle small">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Batch Code</th>
                        <th>Harvest Date</th>
                        <th>Expiry Date</th>
                        <th>Initial Qty</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>`;

        historyBatches.forEach((b, i) => {
            const isArchived = b.batchStatus === "archived";
            const statusLabel = isArchived ? "Archived" : "Depleted / Done";
            const rowClass = isArchived
                ? "table-secondary text-muted"
                : "table-secondary text-muted";
            historyHtml += `
                <tr class="${rowClass}">
                    <td>${i + 1}</td>
                    <td><del><code>${b.batchCode}</code></del></td>
                    <td>${b.harvestDate}</td>
                    <td>${b.expiryDate}</td>
                    <td>${b.initialQty}</td>
                    <td><span class="badge bg-secondary">${statusLabel}</span></td>
                </tr>`;
        });

        historyHtml += "</tbody></table>";
        if (historyBody) {
            historyBody.innerHTML = historyHtml;
        }
    }

    const firstTabEl = document.querySelector(
        '#batchModalTabs button[data-bs-target="#active-batches-pane"]',
    );
    if (firstTabEl) {
        const tabInstance = bootstrap.Tab.getOrCreateInstance(firstTabEl);
        tabInstance.show();
    }

    new bootstrap.Modal(document.getElementById("batchListModal")).show();
}

let currentOrderID = null;
let currentBillID = null;
let confirmModal = null;
let billModal = null;

function viewOrderDetails(orderID) {
    // Determine endpoint based on the current page filename.
    const pageName = window.location.pathname.split("/").pop();
    const endpoint =
        pageName === "orders.php" ? "orders.php" : "hotel_orders.php";

    fetch(`${endpoint}?action=get_order_details&orderID=${orderID}`)
        .then(async (response) => {
            if (!response.ok) {
                const text = await response.text();
                throw new Error(
                    `HTTP ${response.status}: ${response.statusText} - ${text}`,
                );
            }
            return response.json();
        })
        .then((data) => {
            if (data.success) {
                const order = data.order;

                // Store the order ID for later use when completing order
                currentOrderID = order.orderID;

                // Mapping traditional properties
                document.getElementById("modal_orderID").textContent =
                    order.orderID;
                document.getElementById("modal_orderHotel").textContent =
                    order.hotelName;
                document.getElementById("modal_orderDate").textContent =
                    order.orderDate;
                const displayStatus =
                    order.status === "billed" ? "unpaid" : order.status;
                document.getElementById("modal_orderStatus").textContent =
                    displayStatus;
                document.getElementById("modal_orderTotal").textContent =
                    "₱" + parseFloat(order.totalAmount).toFixed(2);

                document.getElementById("modal_currentOrderID").value =
                    order.orderID;

                // Only set cancel button if on hotel_orders page
                const cancelBtn = document.getElementById(
                    "modal_cancelOrderID",
                );
                if (cancelBtn) {
                    cancelBtn.value = order.orderID;
                }

                // Injecting Cart Specific configuration sets
                document.getElementById("modal_orderType").textContent =
                    order.orderType || "Pickup";
                document.getElementById("modal_deliveryTimeSlot").textContent =
                    order.deliveryTimeSlot || "Not Specified";
                document.getElementById("modal_estimatedDelivery").textContent =
                    order.estimatedDelivery || "N/A";

                // Displaying handling days safely
                let dayDiff = parseInt(order.total_days);
                document.getElementById("modal_daysDifference").textContent =
                    !isNaN(dayDiff) && dayDiff >= 0 ? dayDiff : 0;

                // Map order pattern (one-time vs recurring order templates)
                const freqBadge = document.getElementById(
                    "modal_orderFrequency",
                );
                if (order.recurringOrderID) {
                    freqBadge.textContent = "Recurring Order";
                    freqBadge.className = "badge bg-success fs-6";
                } else {
                    freqBadge.textContent = "One-time Order";
                    freqBadge.className = "badge bg-info text-dark fs-6";
                }

                // Loop render items list rows
                let itemsHtml = "";
                data.items.forEach((item) => {
                    let subtotal =
                        parseFloat(item.price) * parseInt(item.quantity);
                    itemsHtml += `
                            <tr>
                                <td>${item.itemName}</td>
                                <td>₱${parseFloat(item.price).toFixed(2)}</td>
                                <td>${item.quantity}</td>
                                <td>₱${subtotal.toFixed(2)}</td>
                            </tr>
                        `;
                });
                document.getElementById("modal_orderItems").innerHTML =
                    itemsHtml;

                // Check if we're on admin page (orders.php) or user page (hotel_orders.php)
                const isAdminPage =
                    window.location.pathname.endsWith("/orders.php") ||
                    window.location.pathname.endsWith("orders.php");

                // Show/hide buttons based on page and order status
                const completeOrderBtn =
                    document.getElementById("completeOrderBtn");
                const viewBillBtn = document.getElementById("viewBillBtn");
                const cancelOrderBtn =
                    document.getElementById("cancelOrderBtn");

                if (isAdminPage && completeOrderBtn && viewBillBtn) {
                    // Admin page button handling
                    if (order.status === "billed" || order.status === "paid") {
                        completeOrderBtn.classList.add("d-none");
                        viewBillBtn.classList.remove("d-none");
                        if (order.billID) {
                            viewBillBtn.onclick = () => viewBill(order.billID);
                        }
                    } else {
                        completeOrderBtn.classList.remove("d-none");
                        viewBillBtn.classList.add("d-none");
                    }
                } else {
                    // User page button handling
                    if (cancelOrderBtn) {
                        if (
                            order.status === "billed" ||
                            order.status === "paid"
                        ) {
                            cancelOrderBtn.classList.add("d-none");
                        } else {
                            cancelOrderBtn.value = order.orderID;
                        }
                    }
                }

                // Programmatically trigger Bootstrap modal structure view
                let targetModal = new bootstrap.Modal(
                    document.getElementById("orderDetailsModal"),
                );
                targetModal.show();
            } else {
                const errorText =
                    data.error || "Could not retrieve target order records.";
                alert("Error: " + errorText);
            }
        })
        .catch((error) => {
            console.error("Fetch Exception Error:", error);
            alert(
                "An unexpected error occurred while loading details: " +
                    error.message,
            );
        });
}

document.addEventListener("DOMContentLoaded", () => {
    const billModalEl = document.getElementById("billPreviewModal");
    if (billModalEl) {
        billModalEl.addEventListener("hidden.bs.modal", () => {
            location.reload();
        });
    }

    const confirmBtn = document.getElementById("confirmCompleteBtn");
    if (confirmBtn) {
        // this prevents crash when button doesn't exist pls don't remove
        confirmBtn.addEventListener("click", () => {
            if (confirmModal) confirmModal.hide();
            processBillOrder();
        });
    }

    const batchDeleteConfirmBtn = document.getElementById(
        "executeBatchDeleteBtn",
    );
    if (batchDeleteConfirmBtn) {
        batchDeleteConfirmBtn.addEventListener("click", () => {
            if (pendingDeleteForm) {
                pendingDeleteForm.submit();
            }
        });
    }
});

function completeOrder() {
    if (!currentOrderID) {
        alert("No order selected");
        return;
    }

    // Show order ID in confirmation modal
    document.getElementById("confirm_orderID").textContent = currentOrderID;

    // Hide order details modal and show confirm modal
    const orderModalEl = document.getElementById("orderDetailsModal");
    const orderModal = bootstrap.Modal.getInstance(orderModalEl);

    if (orderModal) {
        orderModal.hide();
    }

    // Show the confirmation modal after a brief delay to ensure transition
    setTimeout(() => {
        confirmModal = new bootstrap.Modal(
            document.getElementById("confirmCompleteModal"),
        );
        confirmModal.show();
    }, 300);
}

function processBillOrder() {
    const btn = document.getElementById("confirmCompleteBtn");
    btn.disabled = true;
    btn.textContent = "Processing...";

    fetch("complete_order.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "orderID=" + currentOrderID,
    })
        .then((res) => res.json())
        .then((data) => {
            if (data.success) {
                location.reload();
            } else {
                alert("Error: " + (data.error || "Something went wrong"));
                btn.disabled = false;
                btn.textContent = "Yes, Complete Order";
            }
        })
        .catch((err) => {
            console.error(err);
            btn.disabled = false;
            btn.textContent = "Yes, Complete Order";
        });
}

function viewBill(billID) {
    currentBillID = billID;

    fetch("get_bill_details.php?billID=" + billID)
        .then((res) => res.json())
        .then((data) => {
            if (data.error) {
                alert("Error: " + data.error);
                return;
            }

            const bill = data.bill;
            const items = data.items;

            document.getElementById("abill_number").textContent =
                bill.billNumber;
            document.getElementById("abill_hotel").textContent = bill.fullName;
            document.getElementById("abill_date").textContent = new Date(
                bill.billDate,
            ).toLocaleDateString("en-PH", {
                year: "numeric",
                month: "long",
                day: "numeric",
            });
            document.getElementById("abill_due").textContent = new Date(
                bill.dueDate,
            ).toLocaleDateString("en-PH", {
                year: "numeric",
                month: "long",
                day: "numeric",
            });

            const statusColors = {
                paid: "success",
                partial: "warning text-dark",
                unpaid: "danger",
            };
            const color = statusColors[bill.status] || "secondary";
            document.getElementById("abill_status").innerHTML =
                `<span class="badge bg-${color}">${bill.status.toUpperCase()}</span>`;

            const actionBtns = document.getElementById("abill_action_buttons");
            const markPartialBtn = document.getElementById("markPartialBtn");
            const markPaidBtn = document.getElementById("markPaidBtn");

            if (bill.status === "paid") {
                actionBtns.classList.remove("d-none");
                markPartialBtn.classList.add("d-none");
                markPaidBtn.classList.add("d-none");
            } else {
                markPartialBtn.classList.toggle(
                    "d-none",
                    bill.status === "partial",
                );
                markPaidBtn.classList.remove("d-none");
                actionBtns.classList.remove("d-none");
            }

            // Items
            const tbody = document.getElementById("abill_items");
            tbody.innerHTML = "";
            items.forEach((item) => {
                const subtotal = item.price * item.quantity;
                tbody.innerHTML += `
                    <tr>
                        <td>${item.itemName}</td>
                        <td>₱${parseFloat(item.price).toFixed(2)} / ${item.itemUnit}</td>
                        <td>${item.quantity}</td>
                        <td>₱${subtotal.toFixed(2)}</td>
                    </tr>
                `;
            });

            // Totals
            const penalty = parseFloat(bill.penaltyAmount) || 0;
            const total = parseFloat(bill.totalAmount) + penalty;

            document.getElementById("abill_subtotal").textContent =
                "₱" + parseFloat(bill.totalAmount).toFixed(2);
            document.getElementById("abill_total").textContent =
                "₱" + total.toFixed(2);

            const penaltyRow = document.getElementById("abill_penalty_row");
            if (penalty > 0) {
                document.getElementById("abill_penalty").textContent =
                    "₱" + penalty.toFixed(2);
                penaltyRow.classList.remove("d-none");
            } else {
                penaltyRow.classList.add("d-none");
            }
            if (
                bootstrap.Modal.getInstance(
                    document.getElementById("orderDetailsModal"),
                )
            ) {
                bootstrap.Modal.getInstance(
                    document.getElementById("orderDetailsModal"),
                ).hide();
            }

            new bootstrap.Modal(
                document.getElementById("adminBillModal"),
            ).show();
        })
        .catch((err) => console.error("Error fetching bill:", err));
}

function markBillStatus(status) {
    if (!currentBillID) return;

    const label = status === "paid" ? "fully paid" : "partially paid";
    if (!confirm(`Mark this bill as ${label}?`)) return;

    fetch("mark_paid.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: `billID=${currentBillID}&status=${status}`,
    })
        .then((res) => res.json())
        .then((data) => {
            if (data.success) {
                // Update status badge in modal
                const statusColors = {
                    paid: "success",
                    partial: "warning text-dark",
                };
                const color = statusColors[status] || "secondary";
                document.getElementById("abill_status").innerHTML =
                    `<span class="badge bg-${color}">${status.toUpperCase()}</span>`;

                // Hide/update buttons
                const actionBtns = document.getElementById(
                    "abill_action_buttons",
                );
                if (status === "paid") {
                    actionBtns.classList.add("d-none");
                } else {
                    document
                        .getElementById("markPartialBtn")
                        .classList.add("d-none");
                }

                // Reload page in background so table updates when modal closes
                document.getElementById("adminBillModal").addEventListener(
                    "hidden.bs.modal",
                    () => {
                        location.reload();
                    },
                    { once: true },
                );
            } else {
                alert("Error: " + (data.error || "Something went wrong"));
            }
        })
        .catch((err) => console.error("Error:", err));
}

function showBillPreview(bill) {
    // Populate bill info
    document.getElementById("bill_billNumber").textContent = bill.billNumber;
    document.getElementById("bill_customerName").textContent =
        bill.customerName;
    document.getElementById("bill_billDate").textContent = new Date(
        bill.billDate,
    ).toLocaleDateString("en-PH", {
        year: "numeric",
        month: "long",
        day: "numeric",
    });
    document.getElementById("bill_dueDate").textContent = new Date(
        bill.dueDate,
    ).toLocaleDateString("en-PH", {
        year: "numeric",
        month: "long",
        day: "numeric",
    });

    // Populate bill items
    const tbody = document.getElementById("bill_items");
    tbody.innerHTML = "";
    let totalAmount = 0;
    bill.items.forEach((item) => {
        const subtotal = item.price * item.quantity;
        totalAmount += subtotal;
        tbody.innerHTML += `
            <tr>
                <td>${item.itemName}</td>
                <td>₱${parseFloat(item.price).toFixed(2)} / ${item.itemUnit}</td>
                <td>${item.quantity}</td>
                <td>₱${subtotal.toFixed(2)}</td>
            </tr>
        `;
    });

    document.getElementById("bill_totalAmount").textContent =
        "₱" +
        parseFloat(bill.totalAmount).toLocaleString("en-PH", {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });

    // Show the bill preview modal
    new bootstrap.Modal(document.getElementById("billPreviewModal")).show();
}

// Show cart success toast
const urlParams = new URLSearchParams(window.location.search);
const successItem = urlParams.get("success_item");
const successQty = urlParams.get("success_qty");

if (successItem) {
    const toastEl = document.getElementById("cartToast");
    document.getElementById("cartToastMessage").textContent =
        successItem + " x" + successQty + " successfully added to cart!";
    new bootstrap.Toast(toastEl, { delay: 3000 }).show();
}

function showCheckoutConfirm() {
    const form = document.getElementById("checkoutForm");
    const itemCount = form.getAttribute("data-item-count");
    const total = "₱" + form.getAttribute("data-total");
    const orderType = document.querySelector(
        'input[name="orderType"]:checked',
    ).value;

    // Populate modal
    document.getElementById("confirmItemCount").textContent = itemCount;
    document.getElementById("confirmTotal").textContent = total;
    document.getElementById("confirmOrderType").textContent =
        orderType.charAt(0).toUpperCase() + orderType.slice(1);

    // Show modal
    new bootstrap.Modal(document.getElementById("checkoutConfirmModal")).show();
}

// Handle confirm checkout button
const confirmCheckoutBtn = document.getElementById("confirmCheckoutBtn");
if (confirmCheckoutBtn) {
    confirmCheckoutBtn.addEventListener("click", function () {
        const form = document.getElementById("checkoutForm");
        if (form) form.submit();
    });
}

function openEditUserModal(user) {
    const modalEl = document.getElementById("editUserModal");
    const currentUserID = parseInt(
        modalEl.getAttribute("data-current-user-id"),
    );
    const isAdmin = modalEl.getAttribute("data-is-admin") === "1";

    const isEditingSelf = user.userID == currentUserID;
    const isAdminUser = user.role === "admin";

    document.getElementById("editUserID").value = user.userID;
    document.getElementById("editFullName").value = user.fullName;
    document.getElementById("editEmail").value = user.email;
    document.getElementById("editRole").value = user.role;

    const roleSelect = document.getElementById("editRole");
    const roleWarning = document.getElementById("roleWarning");
    const updateBtn = document.getElementById("updateUserBtn");

    // If editing self and self is admin, disable role change
    if (isEditingSelf && isAdmin && isAdminUser) {
        roleSelect.disabled = true;
        roleWarning.style.display = "block";
        updateBtn.disabled = true;
        updateBtn.textContent = "Cannot Edit Own Role";
    } else {
        roleSelect.disabled = false;
        roleWarning.style.display = "none";
        updateBtn.disabled = false;
        updateBtn.textContent = "Update User";
    }

    new bootstrap.Modal(modalEl).show();
}

function editUser(user) {
    const modalEl = document.getElementById("editUserModal");
    const currentUserID = parseInt(
        modalEl.getAttribute("data-current-user-id"),
    );
    const isAdmin = modalEl.getAttribute("data-is-admin") === "1";

    const isEditingSelf = user.userID == currentUserID;
    const isAdminUser = user.role === "admin";

    document.getElementById("editUserID").value = user.userID;
    document.getElementById("editFullName").value = user.fullName;
    document.getElementById("editEmail").value = user.email;
    document.getElementById("editRole").value = user.role;

    const roleSelect = document.getElementById("editRole");
    const roleWarning = document.getElementById("roleWarning");
    const updateBtn = document.getElementById("updateUserBtn");

    // If editing self and self is admin, disable role change
    if (isEditingSelf && isAdmin && isAdminUser) {
        roleSelect.disabled = true;
        roleWarning.style.display = "block";
        updateBtn.disabled = true;
        updateBtn.textContent = "Cannot Edit Own Role";
    } else {
        roleSelect.disabled = false;
        roleWarning.style.display = "none";
        updateBtn.disabled = false;
        updateBtn.textContent = "Update User";
    }
}

function deleteUser(userID) {
    if (confirm("Are you sure you want to delete this user?")) {
        document.getElementById("deleteUserID").value = userID;
        document.getElementById("deleteForm").submit();
    }
}

function calculateSRP() {
    const unitPrice =
        parseFloat(document.getElementById("edit_itemPrice").value) || 0;
    const category = document.getElementById("edit_categoryID").value;
    let srp = 0;

    if (category === "3" || category === "2" || category === "4") {
        srp = unitPrice * 1.1; // 10% markup for fruits, vegetables, and beverages
    } else if (category === "1") {
        srp = unitPrice * 1.2; // 20% markup for dairy products
    }

    const cur = document.getElementById("current_srp");
    if (cur) cur.value = srp;
    const disp = document.getElementById("srpDisplay");
    if (disp)
        disp.textContent =
            "Suggested Retail Price (SRP): ₱ " +
            srp.toLocaleString("en-US", {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });
}
