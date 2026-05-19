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
    document.getElementById("current_srp").value = 0;
    document.getElementById("srpDisplay").textContent = "";

    const editModal = new bootstrap.Modal(
        document.getElementById("editItemModal"),
    );
    editModal.show();
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

function viewOrderDetails(orderID) {
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
            document.getElementById("modal_orderStatus").innerHTML =
                '<span class="badge bg-warning text-dark">Pending</span>';
            document.getElementById("modal_orderTotal").textContent =
                "₱" +
                parseFloat(order.totalAmount).toLocaleString("en-PH", {
                    minimumFractionDigits: 2,
                });

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

            // Show modal
            new bootstrap.Modal(
                document.getElementById("orderDetailsModal"),
            ).show();
        })
        .catch((err) => console.error("Fetch error:", err));
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
