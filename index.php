<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yojak - Accelerating India's Infrastructure</title>
    <style>
        :root {
            --navy-blue: #0a192f;
            --navy-light: #172a45;
            --gold: #d4af37;
            --gold-hover: #b5952f;
            --white: #ffffff;
            --text-grey: #8892b0;
            --glass-bg: rgba(255, 255, 255, 0.05);
            --glass-border: rgba(255, 255, 255, 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }

        body {
            background-color: var(--navy-blue);
            color: var(--white);
            line-height: 1.6;
            overflow-x: hidden;
        }

        a {
            text-decoration: none;
            color: inherit;
            transition: color 0.3s ease;
        }

        /* Navbar */
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem 5%;
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
            background: rgba(10, 25, 47, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--glass-border);
        }

        .logo {
            font-size: 1.8rem;
            font-weight: 700;
            font-family: 'Georgia', serif; /* Serif font as requested */
            color: var(--white);
            letter-spacing: 1px;
        }

        .nav-links {
            display: flex;
            gap: 2rem;
            list-style: none;
        }

        .nav-links a {
            color: var(--text-grey);
            font-weight: 500;
            font-size: 0.95rem;
        }

        .nav-links a:hover {
            color: var(--gold);
        }

        .nav-buttons {
            display: flex;
            gap: 1rem;
        }

        .btn {
            padding: 0.6rem 1.2rem;
            border-radius: 4px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
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
            color: var(--navy-blue);
            border: 1px solid var(--gold);
        }

        .btn-solid:hover {
            background: var(--gold-hover);
            border-color: var(--gold-hover);
        }

        /* Hero Section */
        .hero {
            height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            padding: 0 5%;
            background-image: 
                radial-gradient(circle at 10% 20%, rgba(212, 175, 55, 0.05) 0%, transparent 20%),
                radial-gradient(circle at 90% 80%, rgba(212, 175, 55, 0.05) 0%, transparent 20%);
            /* Geometric pattern simulation */
            background-size: 20px 20px;
            background-position: 0 0, 10px 10px;
        }

        .hero h1 {
            font-size: 3.5rem;
            margin-bottom: 1rem;
            color: var(--white);
            font-weight: 800;
            max-width: 900px;
        }

        .hero p {
            font-size: 1.2rem;
            color: var(--text-grey);
            max-width: 700px;
            margin-bottom: 2.5rem;
        }

        .hero-actions {
            display: flex;
            gap: 1.5rem;
        }

        .btn-large {
            padding: 1rem 2rem;
            font-size: 1.1rem;
        }

        /* Features Grid */
        .features {
            padding: 6rem 5%;
            background: var(--navy-blue);
        }

        .section-title {
            text-align: center;
            margin-bottom: 4rem;
        }

        .section-title h2 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            color: var(--white);
        }

        .section-title p {
            color: var(--gold);
            font-weight: 600;
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        .grid-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }

        .card {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            padding: 2.5rem;
            border-radius: 8px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            backdrop-filter: blur(5px);
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px -15px rgba(2, 12, 27, 0.7);
            border-color: var(--gold);
        }

        .card h3 {
            color: var(--white);
            margin-bottom: 1rem;
            font-size: 1.5rem;
        }

        .card p {
            color: var(--text-grey);
        }

        /* Ecosystem Section */
        .ecosystem {
            padding: 6rem 5%;
            background: var(--navy-light);
            text-align: center;
        }

        .ecosystem-diagram {
            display: flex;
            justify-content: center;
            align-items: center;
            flex-wrap: wrap;
            gap: 3rem;
            margin-top: 3rem;
        }

        .eco-node {
            background: var(--navy-blue);
            padding: 2rem;
            border-radius: 8px;
            border: 1px solid var(--gold);
            min-width: 250px;
            position: relative;
        }

        .eco-node h4 {
            color: var(--gold);
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
            text-transform: uppercase;
        }

        .eco-node p {
            color: var(--white);
            font-size: 1rem;
        }

        .arrow {
            color: var(--text-grey);
            font-size: 2rem;
        }

        /* Footer */
        footer {
            background: var(--navy-blue);
            padding: 4rem 5% 2rem;
            border-top: 1px solid var(--glass-border);
            text-align: center;
        }

        .footer-links {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .footer-links a {
            color: var(--text-grey);
            font-size: 0.9rem;
        }

        .footer-links a:hover {
            color: var(--gold);
        }

        .footer-tagline {
            color: var(--white);
            font-size: 0.9rem;
            opacity: 0.8;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .navbar {
                flex-direction: column;
                gap: 1rem;
                padding: 1rem 5%;
                position: relative; /* Unstick on mobile for better space management */
            }

            .nav-links {
                display: none; /* Hide links on mobile for simplicity in this landing page */
            }

            .hero h1 {
                font-size: 2.5rem;
            }

            .hero-actions {
                flex-direction: column;
                width: 100%;
            }

            .btn-large {
                width: 100%;
            }

            .ecosystem-diagram {
                flex-direction: column;
            }

            .arrow {
                transform: rotate(90deg);
            }
        }
    </style>
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar">
        <div class="logo">YOJAK</div>
        <ul class="nav-links">
            <li><a href="#features">Features</a></li>
            <li><a href="contractor_login.php">For Contractors</a></li>
            <li><a href="login.php">For Departments</a></li>
            <li><a href="#about">About</a></li>
        </ul>
        <div class="nav-buttons">
            <a href="contractor_login.php" class="btn btn-outline">Contractor Login</a>
            <a href="login.php" class="btn btn-solid">Department Login</a>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero">
        <h1>Accelerating India's Infrastructure</h1>
        <p>The intelligent platform that connects Government Divisions with Contractors to automate Tenders, Agreements, and Payments.</p>
        <div class="hero-actions">
            <a href="contractor_login.php" class="btn btn-solid btn-large">I am a Contractor</a>
            <a href="login.php" class="btn btn-outline btn-large">I am an Official</a>
        </div>
    </section>

    <!-- Features Grid -->
    <section id="features" class="features">
        <div class="section-title">
            <p>Why Yojak?</p>
            <h2>Built for Scale & Speed</h2>
        </div>
        <div class="grid-container">
            <div class="card">
                <h3>AI-Powered Drafting</h3>
                <p>Stop typing. Let Yojak draft Agreements, Show Cause Notices, and Letters in seconds.</p>
            </div>
            <div class="card">
                <h3>Universal Vendor ID</h3>
                <p>One verified profile for all your government tenders. No more repetitive paperwork.</p>
            </div>
            <div class="card">
                <h3>The Sealed Room</h3>
                <p>Secure, tamper-proof file movement that mimics the physical green-sheet workflow.</p>
            </div>
        </div>
    </section>

    <!-- Ecosystem Section -->
    <section class="ecosystem">
        <div class="section-title">
            <p>The Ecosystem</p>
            <h2>Seamless Integration</h2>
        </div>
        <div class="ecosystem-diagram">
            <div class="eco-node">
                <h4>Department</h4>
                <p>Issues Work Order</p>
            </div>
            <div class="arrow">&#8594;</div>
            <div class="eco-node" style="border-color: var(--white);">
                <h4 style="color: var(--white);">YOJAK</h4>
                <p>Syncs Agreement, Tracks Delay</p>
            </div>
            <div class="arrow">&#8594;</div>
            <div class="eco-node">
                <h4>Contractor</h4>
                <p>Uploads Bill</p>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="footer-links">
            <a href="#">Privacy Policy</a>
            <a href="#">Terms of Service</a>
            <a href="#">Support</a>
        </div>
        <p class="footer-tagline">Made with 🇮🇳 for Nation Building</p>
    </footer>

</body>
</html>
