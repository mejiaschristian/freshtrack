function loadEditModal(item) {
    document.getElementById("itemID").value = item.itemID;
    document.getElementById("edit_itemName").value = item.itemName;
    document.getElementById("edit_itemDescription").value =
        item.itemDescription;
    document.getElementById("edit_itemCategory").value = item.itemCategory;
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

function calculateSRP() {
    const unitPrice =
        parseFloat(document.getElementById("edit_itemPrice").value) || 0;
    const category = document.getElementById("edit_itemCategory").value;
    let srp = 0;

    if (category === "Fruits" || category === "Vegetables") {
        srp = unitPrice * 1.1; // 10% markup for fruits and vegetables
    } else if (category === "Dairy") {
        srp = unitPrice * 1.2; // 20% markup for dairy products
    }

    document.getElementById("srpDisplay").textContent =
        "Suggested Retail Price (SRP): ₱ " +
        srp.toLocaleString("en-US", {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
    document.getElementById("current_srp").value = srp;
}
