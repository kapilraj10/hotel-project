<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Website Under Development</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
        }
        .dev-box {
            background: #fff;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            max-width: 500px;
            width: 100%;
        }
        .dev-box h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        .dev-box p {
            color: #6c757d;
        }
    </style>
</head>
<body>

<div class="dev-box">
    <h1>🚧 Website Under Development</h1>
    <p class="mb-4">
        Our website is currently under development.<br>
        Please wait, we’ll be launching soon.
    </p>

    <a href="http://localhost/hotel/assets/admin/login.php" class="btn btn-primary px-4">
        Admin Login
    </a>

    <div class="mt-4 text-muted small">
        © <?php echo date('Y'); ?> Your Company Name
    </div>
</div>

</body>
</html>
