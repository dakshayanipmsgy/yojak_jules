from playwright.sync_api import sync_playwright

def run(playwright):
    browser = playwright.chromium.launch(headless=True)
    page = browser.new_page()

    # 1. Register
    page.goto("http://localhost:8000/contractor_register.php")
    page.fill("input[name='company_name']", "Test Construction Ltd")
    page.fill("input[name='owner_name']", "Test Owner")
    page.fill("input[name='mobile']", "9999999999")
    page.fill("input[name='password']", "password123")
    page.fill("input[name='confirm_password']", "password123")
    page.click("button[type='submit']")

    # 2. Login
    # Should redirect to login
    page.wait_for_selector("form[action='contractor_login.php']")
    page.fill("input[name='mobile']", "9999999999")
    page.fill("input[name='password']", "password123")
    page.click("button[type='submit']")

    # 3. Dashboard
    page.wait_for_selector(".dashboard-grid")
    page.screenshot(path="verification/dashboard.png")

    # 4. Profile
    page.click("a[href='contractor_profile.php']")
    page.wait_for_selector("input[name='gst_no']")
    page.fill("input[name='gst_no']", "22AAAAA0000A1Z5")
    page.click("button[type='submit']")

    # Verify success message
    page.wait_for_selector(".main-content")
    page.screenshot(path="verification/profile_saved.png")

    browser.close()

with sync_playwright() as playwright:
    run(playwright)
