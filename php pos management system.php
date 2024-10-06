<?php
session_start();

// Define CSV file paths
define('PRODUCTS_FILE', 'products.csv');
define('CUSTOMERS_FILE', 'customers.csv');
define('SALES_FILE', 'sales.csv');

// Initialize CSV files if they don't exist
foreach ([PRODUCTS_FILE, CUSTOMERS_FILE, SALES_FILE] as $file) {
    if (!file_exists($file)) {
        $handle = fopen($file, 'w');
        fclose($handle);
    }
}

// Handle Logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header("Location: ".$_SERVER['PHP_SELF']);
    exit();
}

// Handle Login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = trim($_POST['username']);
    if ($username === 'Yasin') {
        $_SESSION['loggedin'] = true;
    } else {
        $login_error = "Invalid username.";
    }
}

// Redirect to login if not logged in
if (!isset($_SESSION['loggedin'])) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>POS Login</title>
        <style>
            body { font-family: Arial, sans-serif; background-color: #f2f2f2; }
            .login-container { width: 300px; margin: 100px auto; padding: 20px; background-color: #fff; border-radius: 5px; }
            input[type="text"] { width: 100%; padding: 10px; margin: 5px 0; }
            input[type="submit"] { width: 100%; padding: 10px; background-color: #4CAF50; border: none; color: #fff; }
            .error { color: red; }
        </style>
    </head>
    <body>
        <div class="login-container">
            <h2>Admin Login</h2>
            <?php if (isset($login_error)) echo '<p class="error">'.$login_error.'</p>'; ?>
            <form method="POST">
                <input type="text" name="username" placeholder="Username" required>
                <input type="submit" name="login" value="Login">
            </form>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// Helper Functions
function read_csv($file) {
    $data = [];
    if (($handle = fopen($file, 'r')) !== FALSE) {
        while (($row = fgetcsv($handle)) !== FALSE) {
            $data[] = $row;
        }
        fclose($handle);
    }
    return $data;
}

function write_csv($file, $data) {
    $handle = fopen($file, 'w');
    foreach ($data as $row) {
        fputcsv($handle, $row);
    }
    fclose($handle);
}

function append_csv($file, $data) {
    $handle = fopen($file, 'a');
    fputcsv($handle, $data);
    fclose($handle);
}

// Handle CRUD Operations
// Add Product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    $product = [
        uniqid(),
        trim($_POST['product_name']),
        trim($_POST['category']),
        floatval($_POST['price']),
        intval($_POST['stock'])
    ];
    append_csv(PRODUCTS_FILE, $product);
}

// Edit Product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_product'])) {
    $products = read_csv(PRODUCTS_FILE);
    foreach ($products as &$prod) {
        if ($prod[0] === $_POST['product_id']) {
            $prod[1] = trim($_POST['product_name']);
            $prod[2] = trim($_POST['category']);
            $prod[3] = floatval($_POST['price']);
            $prod[4] = intval($_POST['stock']);
            break;
        }
    }
    write_csv(PRODUCTS_FILE, $products);
}

// Delete Product
if (isset($_GET['delete_product'])) {
    $products = read_csv(PRODUCTS_FILE);
    $products = array_filter($products, function($prod) {
        return $prod[0] !== $_GET['delete_product'];
    });
    write_csv(PRODUCTS_FILE, $products);
    header("Location: ".$_SERVER['PHP_SELF']);
    exit();
}

// Similar CRUD operations can be implemented for Customers and Sales

// Add Customer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_customer'])) {
    $customer = [
        uniqid(),
        trim($_POST['customer_name']),
        trim($_POST['contact'])
    ];
    append_csv(CUSTOMERS_FILE, $customer);
}

// Edit Customer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_customer'])) {
    $customers = read_csv(CUSTOMERS_FILE);
    foreach ($customers as &$cust) {
        if ($cust[0] === $_POST['customer_id']) {
            $cust[1] = trim($_POST['customer_name']);
            $cust[2] = trim($_POST['contact']);
            break;
        }
    }
    write_csv(CUSTOMERS_FILE, $customers);
}

// Delete Customer
if (isset($_GET['delete_customer'])) {
    $customers = read_csv(CUSTOMERS_FILE);
    $customers = array_filter($customers, function($cust) {
        return $cust[0] !== $_GET['delete_customer'];
    });
    write_csv(CUSTOMERS_FILE, $customers);
    header("Location: ".$_SERVER['PHP_SELF']);
    exit();
}

// Add Sale
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_sale'])) {
    $sale = [
        uniqid(),
        trim($_POST['customer_id']),
        trim($_POST['product_id']),
        intval($_POST['quantity']),
        date('Y-m-d H:i:s')
    ];
    append_csv(SALES_FILE, $sale);
    
    // Update product stock
    $products = read_csv(PRODUCTS_FILE);
    foreach ($products as &$prod) {
        if ($prod[0] === $_POST['product_id']) {
            $prod[4] -= intval($_POST['quantity']);
            break;
        }
    }
    write_csv(PRODUCTS_FILE, $products);
}

// Delete Sale
if (isset($_GET['delete_sale'])) {
    $sales = read_csv(SALES_FILE);
    $sale_to_delete = null;
    foreach ($sales as $sale) {
        if ($sale[0] === $_GET['delete_sale']) {
            $sale_to_delete = $sale;
            break;
        }
    }
    if ($sale_to_delete) {
        // Restore product stock
        $products = read_csv(PRODUCTS_FILE);
        foreach ($products as &$prod) {
            if ($prod[0] === $sale_to_delete[2]) {
                $prod[4] += intval($sale_to_delete[3]);
                break;
            }
        }
        write_csv(PRODUCTS_FILE, $products);
        
        // Remove sale
        $sales = array_filter($sales, function($sale) {
            return $sale[0] !== $_GET['delete_sale'];
        });
        write_csv(SALES_FILE, $sales);
    }
    header("Location: ".$_SERVER['PHP_SELF']);
    exit();
}

// Calculate Total Sales
function calculate_total_sales() {
    $sales = read_csv(SALES_FILE);
    $products = [];
    foreach (read_csv(PRODUCTS_FILE) as $prod) {
        $products[$prod[0]] = $prod[3]; // price
    }
    $total = 0;
    foreach ($sales as $sale) {
        if (isset($products[$sale[2]])) {
            $total += $products[$sale[2]] * $sale[3];
        }
    }
    return $total;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>POS Management App</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; }
        .navbar { background-color: #333; color: #fff; padding: 10px; }
        .navbar a { color: #fff; margin-right: 15px; text-decoration: none; }
        .container { padding: 20px; }
        h2 { border-bottom: 1px solid #ccc; padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        table, th, td { border: 1px solid #ccc; }
        th, td { padding: 10px; text-align: left; }
        form { margin-bottom: 20px; }
        input[type="text"], input[type="number"], select { width: 100%; padding: 8px; margin: 5px 0; }
        input[type="submit"] { padding: 10px 20px; background-color: #4CAF50; border: none; color: #fff; cursor: pointer; }
        .error { color: red; }
    </style>
    <script>
        function showForm(formId) {
            document.getElementById('product_form').style.display = 'none';
            document.getElementById('customer_form').style.display = 'none';
            document.getElementById('sale_form').style.display = 'none';
            document.getElementById(formId).style.display = 'block';
        }
    </script>
</head>
<body>
    <div class="navbar">
        <span>POS Management</span>
        <a href="javascript:void(0);" onclick="showForm('product_form')">Products</a>
        <a href="javascript:void(0);" onclick="showForm('customer_form')">Customers</a>
        <a href="javascript:void(0);" onclick="showForm('sale_form')">Sales</a>
        <a href="?action=logout">Logout</a>
    </div>
    <div class="container">
        <!-- Products Section -->
        <div id="product_form">
            <h2>Manage Products</h2>
            <form method="POST">
                <h3>Add Product</h3>
                <input type="text" name="product_name" placeholder="Product Name" required>
                <input type="text" name="category" placeholder="Category" required>
                <input type="number" step="0.01" name="price" placeholder="Price" required>
                <input type="number" name="stock" placeholder="Stock Quantity" required>
                <input type="submit" name="add_product" value="Add Product">
            </form>
            <h3>Product List</h3>
            <table>
                <tr>
                    <th>ID</th><th>Name</th><th>Category</th><th>Price</th><th>Stock</th><th>Actions</th>
                </tr>
                <?php
                $products = read_csv(PRODUCTS_FILE);
                foreach ($products as $prod):
                ?>
                <tr>
                    <td><?= htmlspecialchars($prod[0]) ?></td>
                    <td><?= htmlspecialchars($prod[1]) ?></td>
                    <td><?= htmlspecialchars($prod[2]) ?></td>
                    <td><?= number_format($prod[3], 2) ?></td>
                    <td><?= htmlspecialchars($prod[4]) ?></td>
                    <td>
                        <a href="?edit_product=<?= urlencode($prod[0]) ?>">Edit</a> | 
                        <a href="?delete_product=<?= urlencode($prod[0]) ?>" onclick="return confirm('Delete this product?');">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
            <?php
            // Edit Product Form
            if (isset($_GET['edit_product'])):
                $edit_id = $_GET['edit_product'];
                foreach ($products as $prod) {
                    if ($prod[0] === $edit_id) {
                        $edit_product = $prod;
                        break;
                    }
                }
                if (isset($edit_product)):
            ?>
            <h3>Edit Product</h3>
            <form method="POST">
                <input type="hidden" name="product_id" value="<?= htmlspecialchars($edit_product[0]) ?>">
                <input type="text" name="product_name" value="<?= htmlspecialchars($edit_product[1]) ?>" required>
                <input type="text" name="category" value="<?= htmlspecialchars($edit_product[2]) ?>" required>
                <input type="number" step="0.01" name="price" value="<?= htmlspecialchars($edit_product[3]) ?>" required>
                <input type="number" name="stock" value="<?= htmlspecialchars($edit_product[4]) ?>" required>
                <input type="submit" name="edit_product" value="Update Product">
            </form>
            <?php endif; endif; ?>
        </div>

        <!-- Customers Section -->
        <div id="customer_form" style="display:none;">
            <h2>Manage Customers</h2>
            <form method="POST">
                <h3>Add Customer</h3>
                <input type="text" name="customer_name" placeholder="Customer Name" required>
                <input type="text" name="contact" placeholder="Contact Info" required>
                <input type="submit" name="add_customer" value="Add Customer">
            </form>
            <h3>Customer List</h3>
            <table>
                <tr>
                    <th>ID</th><th>Name</th><th>Contact</th><th>Actions</th>
                </tr>
                <?php
                $customers = read_csv(CUSTOMERS_FILE);
                foreach ($customers as $cust):
                ?>
                <tr>
                    <td><?= htmlspecialchars($cust[0]) ?></td>
                    <td><?= htmlspecialchars($cust[1]) ?></td>
                    <td><?= htmlspecialchars($cust[2]) ?></td>
                    <td>
                        <a href="?edit_customer=<?= urlencode($cust[0]) ?>">Edit</a> | 
                        <a href="?delete_customer=<?= urlencode($cust[0]) ?>" onclick="return confirm('Delete this customer?');">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
            <?php
            // Edit Customer Form
            if (isset($_GET['edit_customer'])):
                $edit_id = $_GET['edit_customer'];
                foreach ($customers as $cust) {
                    if ($cust[0] === $edit_id) {
                        $edit_customer = $cust;
                        break;
                    }
                }
                if (isset($edit_customer)):
            ?>
            <h3>Edit Customer</h3>
            <form method="POST">
                <input type="hidden" name="customer_id" value="<?= htmlspecialchars($edit_customer[0]) ?>">
                <input type="text" name="customer_name" value="<?= htmlspecialchars($edit_customer[1]) ?>" required>
                <input type="text" name="contact" value="<?= htmlspecialchars($edit_customer[2]) ?>" required>
                <input type="submit" name="edit_customer" value="Update Customer">
            </form>
            <?php endif; endif; ?>
        </div>

        <!-- Sales Section -->
        <div id="sale_form" style="display:none;">
            <h2>Manage Sales</h2>
            <form method="POST">
                <h3>Add Sale</h3>
                <label>Customer:</label>
                <select name="customer_id" required>
                    <option value="">Select Customer</option>
                    <?php foreach ($customers as $cust): ?>
                        <option value="<?= htmlspecialchars($cust[0]) ?>"><?= htmlspecialchars($cust[1]) ?></option>
                    <?php endforeach; ?>
                </select>
                <label>Product:</label>
                <select name="product_id" required>
                    <option value="">Select Product</option>
                    <?php foreach ($products as $prod): ?>
                        <option value="<?= htmlspecialchars($prod[0]) ?>"><?= htmlspecialchars($prod[1]) ?> (Stock: <?= htmlspecialchars($prod[4]) ?>)</option>
                    <?php endforeach; ?>
                </select>
                <input type="number" name="quantity" placeholder="Quantity" min="1" required>
                <input type="submit" name="add_sale" value="Add Sale">
            </form>
            <h3>Sales List</h3>
            <table>
                <tr>
                    <th>ID</th><th>Customer</th><th>Product</th><th>Quantity</th><th>Date</th><th>Actions</th>
                </tr>
                <?php
                $sales = read_csv(SALES_FILE);
                foreach ($sales as $sale):
                    // Get customer name
                    $cust_name = '';
                    foreach ($customers as $cust) {
                        if ($cust[0] === $sale[1]) {
                            $cust_name = $cust[1];
                            break;
                        }
                    }
                    // Get product name
                    $prod_name = '';
                    foreach ($products as $prod) {
                        if ($prod[0] === $sale[2]) {
                            $prod_name = $prod[1];
                            break;
                        }
                    }
                ?>
                <tr>
                    <td><?= htmlspecialchars($sale[0]) ?></td>
                    <td><?= htmlspecialchars($cust_name) ?></td>
                    <td><?= htmlspecialchars($prod_name) ?></td>
                    <td><?= htmlspecialchars($sale[3]) ?></td>
                    <td><?= htmlspecialchars($sale[4]) ?></td>
                    <td>
                        <a href="?delete_sale=<?= urlencode($sale[0]) ?>" onclick="return confirm('Delete this sale?');">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
            <h3>Total Sales: $<?= number_format(calculate_total_sales(), 2) ?></h3>
        </div>
    </div>
</body>
</html>
