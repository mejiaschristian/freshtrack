<?php
// Sample inventory data - can be replaced with database queries
function getInventoryItems()
{
    return [
        [
            'id' => 1,
            'name' => 'Tomatoes',
            'description' => 'Fresh red vine tomatoes',
            'category' => 'Vegetables',
            'price' => 3.50,
            'quantity' => 45,
            'unit' => 'kg',
            'expiration_date' => '2026-05-15'
        ],
        [
            'id' => 2,
            'name' => 'Carrots',
            'description' => 'Organic carrots, pesticide-free',
            'category' => 'Vegetables',
            'price' => 2.75,
            'quantity' => 60,
            'unit' => 'kg',
            'expiration_date' => '2026-05-20'
        ],
        [
            'id' => 3,
            'name' => 'Bananas',
            'description' => 'Yellow bananas, ripe and ready',
            'category' => 'Fruits',
            'price' => 1.99,
            'quantity' => 80,
            'unit' => 'kg',
            'expiration_date' => '2026-05-12'
        ],
        [
            'id' => 4,
            'name' => 'Lettuce',
            'description' => 'Crisp green lettuce heads',
            'category' => 'Vegetables',
            'price' => 2.25,
            'quantity' => 35,
            'unit' => 'pieces',
            'expiration_date' => '2026-05-10'
        ],
        [
            'id' => 5,
            'name' => 'Apples',
            'description' => 'Granny Smith apples, sweet and tart',
            'category' => 'Fruits',
            'price' => 4.20,
            'quantity' => 50,
            'unit' => 'kg',
            'expiration_date' => '2026-05-25'
        ],
        [
            'id' => 6,
            'name' => 'Milk',
            'description' => 'Fresh whole milk, 1L',
            'category' => 'Dairy',
            'price' => 5.50,
            'quantity' => 25,
            'unit' => 'liters',
            'expiration_date' => '2026-05-08'
        ],
        [
            'id' => 7,
            'name' => 'Broccoli',
            'description' => 'Fresh green broccoli florets',
            'category' => 'Vegetables',
            'price' => 3.99,
            'quantity' => 28,
            'unit' => 'kg',
            'expiration_date' => '2026-05-11'
        ],
        [
            'id' => 8,
            'name' => 'Eggs',
            'description' => 'Free-range brown eggs, dozen',
            'category' => 'Dairy',
            'price' => 6.75,
            'quantity' => 40,
            'unit' => 'dozen',
            'expiration_date' => '2026-05-18'
        ]
    ];
}
?>
<!doctype html>
<html lang="en" data-bs-theme="light">

<head>
    <title>FreshTrack - Inventory</title>
    <!-- Required meta tags -->
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />

    <!-- Bootstrap CSS v5.3.8 -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
        rel="stylesheet"
        integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB"
        crossorigin="anonymous" />
    <link rel="stylesheet" href="style.css" />
</head>

<body>
    <header class="sticky-top">
        <nav class="navbar navbar-expand-sm navbar-dark bg-success">
            <div class="container-fluid">
                <a class="navbar-brand me-auto" href="#">
                    <img
                        src="fresh-track.png"
                        alt="FreshTrack"
                        class="img-fluid d-block w-auto z-1 mt-2 mx-5" />
                </a>
                <button
                    class="navbar-toggler p-4"
                    type="button"
                    data-bs-toggle="collapse"
                    data-bs-target="#collapsibleNavId"
                    aria-controls="collapsibleNavId"
                    aria-expanded="false"
                    aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="collapsibleNavId">
                    <ul class="navbar-nav ms-auto mt-2 mt-lg-0">
                        <li class="nav-item">
                            <a
                                class="nav-link"
                                href="dashboard.php"
                                aria-current="page">
                                Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="inventory.php">Inventory
                                <span class="visually-hidden">(current)</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="orders.php">Orders</a>
                        </li>
                        <li class="nav-item dropdown">
                            <a
                                class="nav-link dropdown-toggle"
                                href="#"
                                id="dropdownId"
                                data-bs-toggle="dropdown"
                                aria-haspopup="true"
                                aria-expanded="false">
                                More
                            </a>
                            <div
                                class="dropdown-menu"
                                aria-labelledby="dropdownId">
                                <a class="dropdown-item" href="#">Settings</a>
                                <a
                                    class="dropdown-item btn btn-danger"
                                    href="index.php">Log Out</a>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>
    <main>
        <div class="container-fluid mt-5">
            <h2>Inventory</h2>
            <p>Manage your inventory here.</p>
            <div class="mt-5">
                <div class="card mb-4">
                    <h3 class="card-header bg-success-subtle">
                        <div class="row">
                            <div class="col">
                                <search>
                                    <input type="text" class="form-control" placeholder="Search inventory..." />
                                </search>
                            </div>
                            <div class="col">
                                <div class="btn-group w-100">
                                    <button
                                        class="btn btn-secondary dropdown-toggle"
                                        type="button"
                                        id="triggerId"
                                        data-bs-toggle="dropdown"
                                        aria-haspopup="true"
                                        aria-expanded="false">
                                        Filter Column
                                    </button>
                                    <div
                                        class="dropdown-menu w-100"
                                        aria-labelledby="triggerId">
                                        <a class="dropdown-item" href="#">Action</a>
                                        <a class="dropdown-item" href="#">Action</a>
                                        <a class="dropdown-item" href="#">Action</a>
                                    </div>
                                </div>

                            </div>
                            <div class="col">
                                <button class="btn btn-success w-100">Add Item</button>
                            </div>
                        </div>
                    </h3>
                    <div class="inventory card-body p-0">
                        <table class="table table-striped table-hover">
                            <thead class="table-secondary">
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Description</th>
                                    <th>Category</th>
                                    <th>Price</th>
                                    <th>Quantity</th>
                                    <th>Unit</th>
                                    <th>Expiration</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $items = getInventoryItems();
                                foreach ($items as $item):
                                ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['id']); ?></td>
                                        <td><strong><?php echo htmlspecialchars($item['name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($item['description']); ?></td>
                                        <td>
                                            <span class="badge bg-success-subtle text-success">
                                                <?php echo htmlspecialchars($item['category']); ?>
                                            </span>
                                        </td>
                                        <td>$<?php echo number_format($item['price'], 2); ?></td>
                                        <td><?php echo $item['quantity']; ?></td>
                                        <td><?php echo htmlspecialchars($item['unit']); ?></td>
                                        <td>
                                            <small class="text-muted">
                                                <?php echo date('M d, Y', strtotime($item['expiration_date'])); ?>
                                            </small>
                                        </td>
                                        <td class="d-flex gap-2">
                                            <button class="btn btn-sm btn-primary">Edit</button>
                                            <button class="btn btn-sm btn-danger">Delete</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
    </main>
    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
        crossorigin="anonymous"></script>
</body>

</html>