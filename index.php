<?php
session_start();
// If logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yojak - Accelerating India's Infrastructure</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --navy-deep: #0a192f;
            --navy-light: #172a45;
            --gold: #d4af37;
            --gold-light: #f3e5ab;
            --white: #ffffff;
            --text-gray: #8892b0;
            --glass-bg: rgba(255, 255, 255, 0.05);
            --glass-border: rgba(255, 255, 255, 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--navy-deep);
            color: var(--white);
            line-height: 1.6;
            overflow-x: hidden;
        }

        a {
            text-decoration: none;
            color: inherit;
            transition: all 0.3s ease;
        }

        /* Navbar */
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 5%;
            background: rgba(10, 25, 47, 0.95);
            backdrop-filter: blur(10px);
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
            border-bottom: 1px solid var(--glass-border);
        }

        .logo {
            font-family: 'Playfair Display', serif;
            font-size: 2rem;
            font-weight: 700;
            color: var(--white);
            letter-spacing: 1px;
        }

        .nav-links {
            display: flex;
            gap: 30px;
            align-items: center;
        }

        .nav-link {
            color: var(--text-gray);
            font-size: 0.95rem;
            font-weight: 500;
        }

        .nav-link:hover {
            color: var(--gold);
        }

        .nav-buttons {
            display: flex;
            gap: 15px;
        }

        .btn {
            padding: 10px 24px;
            border-radius: 4px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .btn-outline {
            border: 1px solid var(--gold);
            color: var(--gold);
            background: transparent;
        }

        .btn-outline:hover {
            background: rgba(212, 175, 55, 0.1);
        }

        .btn-solid {
            background: var(--gold);
            color: var(--navy-deep);
            border: 1px solid var(--gold);
        }

        .btn-solid:hover {
            background: var(--gold-light);
            border-color: var(--gold-light);
        }

        /* Hero Section */
        .hero {
            height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            padding: 0 20px;
            background: radial-gradient(circle at 50% 50%, #172a45 0%, #0a192f 100%);
            position: relative;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: linear-gradient(rgba(255, 255, 255, 0.03) 1px, transparent 1px),
            linear-gradient(90deg, rgba(255, 255, 255, 0.03) 1px, transparent 1px);
            background-size: 50px 50px;
            opacity: 0.3;
            pointer-events: none;
        }

        .hero-content {
            z-index: 1;
            max-width: 800px;
            animation: fadeIn Up 1s ease-out;
        }

        .hero h1 {
            font-family: 'Playfair Display', serif;
            font-size: 3.5rem;
            margin-bottom: 20px;
            color: var(--white);
            line-height: 1.2;
        }

        .hero p {
            color: var(--text-gray);
            font-size: 1.2rem;
            margin-bottom: 40px;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        .hero-buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
        }

        .btn-large {
            padding: 15px 35px;
            font-size: 1.1rem;
        }

        /* Features Section */
        .features {
            padding: 100px 5%;
            background-color: var(--navy-deep);
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .feature-card {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            padding: 40px;
            border-radius: 8px;
            transition: transform 0.3s ease;
        }

        .feature-card:hover {
            transform: translateY(-5px);
            border-color: var(--gold);
        }

        .feature-title {
            color: var(--gold);
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem;
            margin-bottom: 15px;
        }

        .feature-text {
            color: var(--text-gray);
        }

        /* Ecosystem Section */
        .ecosystem {
            padding: 100px 5%;
            background-color: var(--navy-light);
            text-align: center;
        }

        .ecosystem-title {
            font-family: 'Playfair Display', serif;
            font-size: 2.5rem;
            margin-bottom: 60px;
            color: var(--white);
        }

        .ecosystem-flow {
            display: flex;
            justify-content: center;
            align-items: center;
            flex-wrap: wrap;
            gap: 40px;
            max-width: 1000px;
            margin: 0 auto;
        }

        .flow-node {
            background: var(--glass-bg);
            border: 1px solid var(--gold);
            padding: 30px;
            border-radius: 50%;
            width: 200px;
            height: 200px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        .flow-node strong {
            display: block;
            font-size: 1.2rem;
            margin-bottom: 10px;
            color: var(--white);
        }

        .flow-node span {
            font-size: 0.9rem;
            color: var(--text-gray);
        }

        .flow-arrow {
            color: var(--gold);
            font-size: 2rem;
            font-weight: bold;
        }

        /* Footer */
        .footer {
            padding: 40px 5%;
            background-color: #050d1a;
            border-top: 1px solid var(--glass-border);
            text-align: center;
            color: var(--text-gray);
        }

        .footer-links {
            margin-bottom: 20px;
        }

        .footer-links a {
            margin: 0 15px;
            font-size: 0.9rem;
        }

        .footer-links a:hover {
            color: var(--gold);
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .navbar {
                flex-direction: column;
                gap: 20px;
                padding: 15px;
            }

            .nav-links {
                display: none; /* Hide standard links on mobile for simplicity */
            }

            .hero h1 {
                font-size: 2.5rem;
            }

            .hero-buttons {
                flex-direction: column;
            }

            .ecosystem-flow {
                flex-direction: column;
            }

            .flow-arrow {
                transform: rotate(90deg);
            }
        }
    </style>
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar">
        <div class="logo">YOJAK</div>
        <div class="nav-links">
            <a href="#features" class="nav-link">Features</a>
            <a href="#ecosystem" class="nav-link">For Departments</a>
            <a href="#ecosystem" class="nav-link">For Contractors</a>
        </div>
        <div class="nav-buttons">
            <a href="contractor_login.php" class="btn btn-outline">Contractor Login</a>
            <a href="dept_login.php" class="btn btn-solid">Department Login</a>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-content">
            <h1>Accelerating India's Infrastructure.</h1>
            <p>The intelligent platform that connects Government Divisions with Contractors to automate Tenders, Agreements, and Payments.</p>
            <div class="hero-buttons">
                <a href="contractor_login.php" class="btn btn-outline btn-large">I am a Contractor</a>
                <a href="dept_login.php" class="btn btn-solid btn-large">I am an Official</a>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="features">
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-title">AI-Powered Drafting</div>
                <div class="feature-text">Stop typing. Let Yojak draft Agreements, Show Cause Notices, and Letters in seconds using advanced document intelligence.</div>
            </div>
            <div class="feature-card">
                <div class="feature-title">Universal Vendor ID</div>
                <div class="feature-text">One verified profile for all your government tenders. No more repetitive paperwork. Seamlessly link across departments.</div>
            </div>
            <div class="feature-card">
                <div class="feature-title">The Sealed Room</div>
                <div class="feature-text">Secure, tamper-proof file movement that mimics the physical green-sheet workflow. Only the owner can view or edit.</div>
            </div>
        </div>
    </section>

    <!-- Ecosystem Section -->
    <section id="ecosystem" class="ecosystem">
        <h2 class="ecosystem-title">The Yojak Ecosystem</h2>
        <div class="ecosystem-flow">
            <div class="flow-node">
                <strong>Department</strong>
                <span>Issues Work Order</span>
            </div>
            <div class="flow-arrow">&rarr;</div>
            <div class="flow-node" style="border-color: var(--white); background: rgba(255,255,255,0.1);">
                <strong style="color: var(--gold);">YOJAK</strong>
                <span>Syncs Agreements<br>Tracks Delays</span>
            </div>
            <div class="flow-arrow">&rarr;</div>
            <div class="flow-node">
                <strong>Contractor</strong>
                <span>Uploads Bill</span>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-links">
            <a href="#">Privacy Policy</a>
            <a href="#">Terms of Service</a>
            <a href="#">Support</a>
        </div>
        <p>Made with 🇮🇳 for Nation Building.</p>
    </footer>

</body>
</html>
