function loadEditModal(item) {
    document.getElementById("itemID").value = item.itemID;
    document.getElementById("edit_itemName").value = item.itemName;
    document.getElementById("edit_itemDescription").value =
        item.itemDescription;
    document.getElementById("edit_categoryID").value = item.categoryID;
    document.getElementById("edit_itemPrice").value = item.itemPrice;
    document.getElementById("edit_itemQuantity").value = item.itemQuantity;
    document.getElementById("edit_itemUnit").value = item.itemUnit;
    document.getElementById("edit_itemDateAdded").value = item.itemDateAdded;
    document.getElementById("edit_itemExpiryDate").value = item.itemExpiryDate;
    document.getElementById("edit_existingImage").value = item.itemImage ?? ""; // preserve existing image
    document.getElementById("current_srp").value = 0;
    document.getElementById("srpDisplay").textContent = "";

    new bootstrap.Modal(document.getElementById("editItemModal")).show();
}

function setDeleteItemID(itemID, itemName) {
    document.getElementById("deleteItemID").value = itemID;
    document.getElementById("deleteItemName").value = itemName;
    document.getElementById("deleteItemNameDisplay").textContent = itemName;
}

function openCartModal(item) {
    // Populate modal fields
    document.getElementById("modal_itemID").value = item.itemID;
    document.getElementById("modal_itemName").textContent = item.itemName;
    document.getElementById("modal_itemDescription").textContent =
        item.itemDescription;
    document.getElementById("modal_itemPrice").textContent =
        "₱" + parseFloat(item.itemPrice).toFixed(2) + " / " + item.itemUnit;
    document.getElementById("modal_itemQuantity").textContent =
        item.itemQuantity + " " + item.itemUnit;
    document.getElementById("modal_itemImage").src =
        item.itemImage || "placeholder.png";

    // Reset quantity to 1
    const qtyInput = document.getElementById("modal_quantity");
    qtyInput.value = 1;
    qtyInput.max = item.itemQuantity; // can't exceed stock

    // Set subtotal
    document.getElementById("modal_subtotal").textContent =
        "₱" + parseFloat(item.itemPrice).toFixed(2);

    // Update subtotal when quantity changes
    qtyInput.oninput = function () {
        const total = this.value * parseFloat(item.itemPrice);
        document.getElementById("modal_subtotal").textContent =
            "₱" + total.toFixed(2);
    };

    // Show modal
    new bootstrap.Modal(document.getElementById("addToCartModal")).show();
}

let currentOrderID = null;
let currentBillID = null;
let confirmModal = null;
let billModal = null;

function viewOrderDetails(orderID) {
    currentOrderID = orderID;

    fetch("order_details.php?orderID=" + orderID)
        .then((res) => res.json())
        .then((data) => {
            if (data.error) {
                alert("Error: " + data.error);
                return;
            }

            const order = data.order;
            const items = data.items;

            // Populate order info
            document.getElementById("modal_orderID").textContent =
                order.orderID;
            document.getElementById("modal_orderHotel").textContent =
                order.fullName;
            document.getElementById("modal_orderDate").textContent = new Date(
                order.orderDate,
            ).toLocaleDateString("en-PH", {
                year: "numeric",
                month: "long",
                day: "numeric",
            });
            document.getElementById("modal_orderTotal").textContent =
                "₱" +
                parseFloat(order.totalAmount).toLocaleString("en-PH", {
                    minimumFractionDigits: 2,
                });

            // Status badge
            const statusColors = {
                pending: "warning",
                billed: "success",
                paid: "info",
            };
            const color = statusColors[order.status] || "secondary";
            document.getElementById("modal_orderStatus").innerHTML =
                `<span class="badge bg-${color} text-dark">${order.status.toUpperCase()}</span>`;

            // Show/hide buttons based on status
            const completeBtn = document.getElementById("completeOrderBtn");
            const viewBillBtn = document.getElementById("viewBillBtn");

            if (order.status === "pending") {
                completeBtn.classList.remove("d-none");
                viewBillBtn.classList.add("d-none");
            } else {
                completeBtn.classList.add("d-none");
                // Show view bill if billed
                if (data.billID) {
                    viewBillBtn.classList.remove("d-none");
                    viewBillBtn.onclick = () => viewBill(data.billID);
                    viewBillBtn.href = "#";
                }
            }

            // Populate items
            const tbody = document.getElementById("modal_orderItems");
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

            new bootstrap.Modal(
                document.getElementById("orderDetailsModal"),
            ).show();
        })
        .catch((err) => console.error("Fetch error:", err));
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
        confirmBtn.addEventListener("click", () => {
            if (confirmModal) confirmModal.hide();
            processBillOrder();
        });
    }
});

function completeOrder() {
    if (!currentOrderID) return;

    // Show order ID in confirmation modal
    document.getElementById("confirm_orderID").textContent = currentOrderID;

    // Hide order details modal first, then show confirm modal
    const orderModalEl = document.getElementById("orderDetailsModal");
    const orderModal = bootstrap.Modal.getInstance(orderModalEl);

    orderModalEl.addEventListener(
        "hidden.bs.modal",
        () => {
            confirmModal = new bootstrap.Modal(
                document.getElementById("confirmCompleteModal"),
            );
            confirmModal.show();
        },
        { once: true },
    );

    orderModal.hide();
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
    currentBillID = billID; // track it

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

            // Status badge
            const statusColors = {
                paid: "success",
                partial: "warning text-dark",
                unpaid: "danger",
            };
            const color = statusColors[bill.status] || "secondary";
            document.getElementById("abill_status").innerHTML =
                `<span class="badge bg-${color}">${bill.status.toUpperCase()}</span>`;

            // Show/hide action buttons based on status
            const actionBtns = document.getElementById("abill_action_buttons");
            if (bill.status === "paid") {
                actionBtns.classList.add("d-none"); // already paid, hide buttons
            } else {
                actionBtns.classList.remove("d-none");
                // Hide "Mark Partial" if already partial
                document
                    .getElementById("markPartialBtn")
                    .classList.toggle("d-none", bill.status === "partial");
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

    document.getElementById("current_srp").value = srp;
    document.getElementById("srpDisplay").textContent =
        "Suggested Retail Price (SRP): ₱ " +
        srp.toLocaleString("en-US", {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
}
