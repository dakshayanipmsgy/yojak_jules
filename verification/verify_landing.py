from playwright.sync_api import sync_playwright

def verify_landing_page():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()

        # Navigate to index.php
        page.goto("http://localhost:8000/index.php")

        # Check title
        assert "Yojak - Accelerating India's Infrastructure" in page.title()

        # Check hero text
        assert page.is_visible("text=Accelerating India's Infrastructure")

        # Check login buttons
        assert page.is_visible("text=Department Login")
        assert page.is_visible("text=Contractor Login")

        # Take full page screenshot
        page.screenshot(path="verification/landing_page.png", full_page=True)

        # Click Department Login and verify redirection
        page.click("text=Department Login")
        # Should be on dept_login.php
        # Note: Depending on how php server serves files, it might show dept_login.php in url or just render it.
        # Since we redirect, URL should change.
        assert "dept_login.php" in page.url

        # Go back
        page.go_back()

        # Click Contractor Login
        page.click("text=Contractor Login")
        assert "contractor_login.php" in page.url

        browser.close()

if __name__ == "__main__":
    verify_landing_page()
