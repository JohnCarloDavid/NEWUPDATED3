<?php
// Start the session
session_start();

// Include database connection file
include('db_connection.php');

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $customer_name = $_POST['customer_name'];
    $order_date = $_POST['order_date'];

    // Begin transaction
    $conn->begin_transaction();

    try {
        // Iterate over products to process each item in the order
        foreach ($_POST['product_name'] as $index => $product_name) {
            $size = $_POST['size'][$index];
            $quantity = $_POST['quantity'][$index];
            $status = isset($_POST['status'][$index]) ? $_POST['status'][$index] : 'Pending';

            // Fetch the product price from the inventory table
            $price_sql = "SELECT price FROM tb_inventory WHERE name = ? AND size = ?";
            $price_stmt = $conn->prepare($price_sql);
            $price_stmt->bind_param('ss', $product_name, $size);
            $price_stmt->execute();
            $price_result = $price_stmt->get_result();

            if ($price_result->num_rows === 0) {
                throw new Exception("Product price not found for $product_name ($size).");
            }

            $price_row = $price_result->fetch_assoc();
            $price = $price_row['price']; // Price of the selected product
            
            // Insert each product into the orders table
            $sql = "INSERT INTO tb_orders (customer_name, product_name, size, quantity, order_date, status) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('sssiss', $customer_name, $product_name, $size, $quantity, $order_date, $status);

            if (!$stmt->execute()) {
                throw new Exception("Error adding order.");
            }

            // Retrieve and update inventory quantity
            $inventory_sql = "SELECT quantity FROM tb_inventory WHERE name = ? AND size = ?";
            $inventory_stmt = $conn->prepare($inventory_sql);
            $inventory_stmt->bind_param('ss', $product_name, $size);
            $inventory_stmt->execute();
            $inventory_result = $inventory_stmt->get_result();

            if ($inventory_result->num_rows === 0) {
                throw new Exception("Product not found in inventory.");
            }

            $inventory_row = $inventory_result->fetch_assoc();
            $current_quantity = $inventory_row['quantity'];

            if ($current_quantity < $quantity) {
                throw new Exception("Not enough stock for $product_name ($size).");
            }

            $new_quantity = $current_quantity - $quantity;
            $update_inventory_sql = "UPDATE tb_inventory SET quantity = ? WHERE name = ? AND size = ?";
            $update_inventory_stmt = $conn->prepare($update_inventory_sql);
            $update_inventory_stmt->bind_param('iss', $new_quantity, $product_name, $size);

            if (!$update_inventory_stmt->execute()) {
                throw new Exception("Error updating inventory.");
            }
        }

        $conn->commit();
        header('Location: orders.php');
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        echo "<script>alert('" . $e->getMessage() . "'); window.location.href = 'add-order.php';</script>";
    }
}

// Fetch product names and prices for the select dropdown
$product_sql = "SELECT DISTINCT name FROM tb_inventory";
$product_result = $conn->query($product_sql);

// Fetch size and price information for each product
$size_sql = "SELECT name, size, price FROM tb_inventory";
$size_result = $conn->query($size_sql);

$product_sizes = [];
while ($row = $size_result->fetch_assoc()) {
    $product_name = $row['name'];
    $size = $row['size'];
    $price = $row['price'];
    
    if (!isset($product_sizes[$product_name])) {
        $product_sizes[$product_name] = [];
    }
    $product_sizes[$product_name][] = ['size' => $size, 'price' => $price];
}
$product_sizes_json = json_encode($product_sizes);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Order - GSL25 Inventory Management System</title>
    <link rel="icon" href="img/GSL25_transparent 2.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css">
    <style>
        /* General Styles */
body {
    font-family: 'Poppins', sans-serif;
    background:   #ffffff;
    color: #333;
    transition: background-color 0.3s, color 0.3s;
}

body.dark-mode {
    background: #2c3e50;
    color: #ecf0f1;
}

/* Container Styles */
.container {
    max-width: 800px;
    margin: 20px auto;
    padding: 20px;
    background: #ffffff;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    transition: background 0.3s, color 0.3s;
}

.container.dark-mode {
    background: #34495e;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3); /* More prominent shadow */
}

/* Header */
h1 {
    font-size: 28px;
    margin-bottom: 20px;
    color: #007bff;
}

body.dark-mode h1 {
    color: #2980b9; /* Lighter blue in dark mode */
}

/* Form Element Styles */
form label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
}

form input[type="text"],
form input[type="number"],
form input[type="date"],
form select {
    width: 100%;
    padding: 10px;
    margin-bottom: 15px;
    border: 1px solid #ced4da;
    border-radius: 4px;
    color: #333;
    background-color: #ffffff;
    transition: background-color 0.3s, color 0.3s;
}


body.dark-mode form input[type="text"],
body.dark-mode form input[type="number"],
body.dark-mode form input[type="date"],
body.dark-mode form select {
    color: #ecf0f1;
    background-color: #2c3e50;
    border: 1px solid #444;
}

/* Button Styles */
button, .backButton {
    padding: 10px 15px;
    border-radius: 4px;
    font-size: 16px;
    display: inline-block;
    cursor: pointer;
    transition: background-color 0.3s;
}

button {
    background-color: #007bff;
    color: #ffffff;
    border: none;
}

button:hover {
    background-color: #0056b3;
}

body.dark-mode button {
    background-color: #2980b9;
}

body.dark-mode button:hover {
    background-color: #1f5a85;
}

.backButton {
    background-color: #e74c3c;
    color: #ffffff;
}

.backButton:hover {
    background-color: red;
}

body.dark-mode .backButton {
    background-color: #c0392b;
}

body.dark-mode .backButton:hover {
    background-color: #a93226;
}

/* Product Entry Styles */
.product-entry {
    border: 1px solid #ddd;
    padding: 10px;
    margin-bottom: 10px;
    border-radius: 8px;
    background-color: #f9f9f9;
    position: relative;
}

body.dark-mode .product-entry {
    background-color: #3b3b3b;
    border: 1px solid #555; /* Darker border in dark mode */
}

.product-entry button.remove-product {
    position: absolute;
    top: 5px;
    right: 5px;
    background-color: #e74c3c;
    color: white;
    padding: 5px;
    border-radius: 4px;
}

.product-entry button.remove-product:hover {
    background-color: #c0392b;
}

body.dark-mode .product-entry button.remove-product {
    background-color: #c0392b;
}

body.dark-mode .product-entry button.remove-product:hover {
    background-color: #e74c3c;
}

/* Dark Mode Toggle */
.dark-mode-toggle {
    position: fixed;
    top: 20px;
    right: 20px;
    background-color: #3498db;
    color: #ffffff;
    border: none;
    padding: 10px 15px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 16px;
    font-weight: bold;
}

.dark-mode-toggle:hover {
    background-color: #2980b9;
}

body.dark-mode .dark-mode-toggle {
    background-color: #1abc9c; /* Lighter teal for the dark mode toggle */
}

body.dark-mode .dark-mode-toggle:hover {
    background-color: #16a085;
}

    </style>
</head>
<body>

<div class="container">
    <h1 class="text-2xl font-bold mb-4">Add New Order</h1>
    <form action="add-order.php" method="POST">
        <label for="customer_name">Customer Name:</label>
        <input type="text" id="customer_name" name="customer_name" required>

        <label for="order_date">Order Date:</label>
        <input type="date" id="order_date" name="order_date" required>

        <div id="products-container">
    <div class="product-entry">
        <label for="product_name">Product Name:</label>
        <select name="product_name[]" required onchange="updateSizes(this)">
            <option value="">Select Product</option>
            <?php while ($product_row = $product_result->fetch_assoc()): ?>
                <option value="<?php echo htmlspecialchars($product_row['name']); ?>">
                    <?php echo htmlspecialchars($product_row['name']); ?>
                </option>
            <?php endwhile; ?>
        </select>

        <label for="size">Size:</label>
        <select name="size[]" required>
            <option value="">Select Size</option>
        </select>

        <label for="quantity">Quantity:</label>
        <input type="number" name="quantity[]" required>

        <label for="price">Price:</label>
        <input type="text" name="price[]" readonly>

        <button type="button" class="remove-product" onclick="removeProductEntry(this)">Remove</button>
    </div>
</div>



        <button type="button" onclick="addProductEntry()">Add Another Product</button>

        <div class="button-container mt-6">
            <button type="submit">Save Order</button>
                <a href="orders.php" class="backButton">Back to Orders</a>
            <!-- Print Receipt Button -->
            <button type="button" onclick="generateReceipt()">Print Receipt</button>
        </div>
    </form>
</div>

<script>
    const productSizes = <?php echo $product_sizes_json; ?>;

    // Function to update sizes and prices based on selected product
function updateSizes(selectElement) {
    var productName = selectElement.value;
    var sizeSelect = selectElement.closest('.product-entry').querySelector('select[name="size[]"]');
    var priceInput = selectElement.closest('.product-entry').querySelector('input[name="price[]"]');

    // Clear previous size options
    sizeSelect.innerHTML = '<option value="">Select Size</option>';
    priceInput.value = ''; // Clear price input

    // Get the sizes and prices for the selected product
    var productSizes = <?php echo $product_sizes_json; ?>;
    if (productSizes[productName]) {
        productSizes[productName].forEach(function(item) {
            var option = document.createElement('option');
            option.value = item.size;
            option.textContent = item.size + ' - ₱' + item.price;
            sizeSelect.appendChild(option);
        });
    }

    // Update price field based on selected size
    sizeSelect.onchange = function() {
        var selectedSize = sizeSelect.value;
        if (selectedSize) {
            var selectedItem = productSizes[productName].find(function(item) {
                return item.size === selectedSize;
            });
            priceInput.value = selectedItem ? '₱' + selectedItem.price : '';
        }
    };
}

    function addProductEntry() {
        const container = document.getElementById('products-container');
        const newEntry = document.querySelector('.product-entry').cloneNode(true);
        newEntry.querySelector('select[name="product_name[]"]').value = "";
        newEntry.querySelector('select[name="size[]"]').innerHTML = "";
        newEntry.querySelector('input[name="quantity[]"]').value = "";
        container.appendChild(newEntry);
    }

    function removeProductEntry(button) {
        const container = document.getElementById('products-container');
        if (container.children.length > 1) {
            button.closest('.product-entry').remove();
        } else {
            alert("At least one product must be included in the order.");
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelector('select[name="product_name[]"]').dispatchEvent(new Event('change'));
        if (localStorage.getItem('darkMode') === 'enabled') {
            document.body.classList.add('dark-mode');
            document.querySelector('.container').classList.add('dark-mode');
        }
    });

    function toggleDarkMode() {
        document.body.classList.toggle('dark-mode');
        document.querySelector('.container').classList.toggle('dark-mode');
        localStorage.setItem('darkMode', document.body.classList.contains('dark-mode') ? 'enabled' : 'disabled');
    }
    
    function generateReceipt() {
    const customerName = document.getElementById('customer_name').value;
    const orderDate = document.getElementById('order_date').value;
    const products = document.querySelectorAll('.product-entry');

    let receiptHtml = `
        <div style="font-family: Arial, sans-serif; padding: 20px; border: 1px solid #ddd; background-color: #f4f4f4;">
            <h2 style="text-align: center;">Order Receipt</h2>
            <p><strong>Customer Name:</strong> ${customerName}</p>
            <p><strong>Order Date:</strong> ${orderDate}</p>
            <table style="width: 100%; margin-top: 20px; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th style="border: 1px solid #ddd; padding: 8px;">Product Name</th>
                        <th style="border: 1px solid #ddd; padding: 8px;">Size</th>
                        <th style="border: 1px solid #ddd; padding: 8px;">Quantity</th>
                        <th style="border: 1px solid #ddd; padding: 8px;">Price</th>
                        <th style="border: 1px solid #ddd; padding: 8px;">Total</th>
                    </tr>
                </thead>
                <tbody>
    `;

    let totalAmount = 0; // Initialize total amount

    products.forEach(product => {
        const productName = product.querySelector('select[name="product_name[]"]').value;
        const sizeSelect = product.querySelector('select[name="size[]"]');
        const selectedSize = sizeSelect.options[sizeSelect.selectedIndex];
        const size = selectedSize ? selectedSize.value : '';
        const quantity = parseInt(product.querySelector('input[name="quantity[]"]').value, 10);
        const priceText = selectedSize ? selectedSize.textContent.split('₱')[1] : '0';
        const price = parseFloat(priceText) || 0;

        // Calculate total price for the current product
        const totalPrice = price * quantity;

        receiptHtml += `
            <tr>
                <td style="border: 1px solid #ddd; padding: 8px;">${productName}</td>
                <td style="border: 1px solid #ddd; padding: 8px;">${size}</td>
                <td style="border: 1px solid #ddd; padding: 8px;">${quantity}</td>
                <td style="border: 1px solid #ddd; padding: 8px;">₱${price.toFixed(2)}</td>
                <td style="border: 1px solid #ddd; padding: 8px;">₱${totalPrice.toFixed(2)}</td>
            </tr>
        `;

        totalAmount += totalPrice; // Accumulate total amount
    });

    receiptHtml += `
        </tbody>
    </table>
    <p style="margin-top: 20px; text-align: right;"><strong>Total Amount: </strong>₱${totalAmount.toFixed(2)}</p>
    <div style="text-align: center; margin-top: 20px;">
        <button onclick="window.print()" style="padding: 10px 15px; background-color: #3498db; color: white; border: none; cursor: pointer;">Print Receipt</button>
    </div>
</div>
    `;

    // Open the receipt in a new window for printing
    const receiptWindow = window.open('', '', 'width=800,height=600');
    receiptWindow.document.write(receiptHtml);
    receiptWindow.document.close();
    receiptWindow.focus();
}


</script>

</body>
</html>