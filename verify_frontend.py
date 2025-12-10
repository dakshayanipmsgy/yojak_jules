from playwright.sync_api import sync_playwright, expect

def run(playwright):
    browser = playwright.chromium.launch(headless=True)
    page = browser.new_page()

    # Route get_login_roles.php to return mock data
    def handle_route(route):
        route.fulfill(
            status=200,
            content_type="application/json",
            body='[{"id":"admin.dws", "name":"Administrator"}]'
        )

    page.route("**/get_login_roles.php?dept_id=dws", handle_route)

    # Load the test file
    # Assuming the python server is running on port 8000
    page.goto("http://localhost:8000/test_index.html")

    # Verify Box 1: Dept ID
    dept_input = page.locator("#dept_id_search")

    # Check attributes
    # expect(dept_input).to_have_attribute("autocomplete", "off") # Playwright check for attribute
    # expect(dept_input).to_have_attribute("readonly", "") # readonly attribute presence

    # Focus and check readonly removed
    dept_input.focus()
    # After focus, readonly should be removed. We can check by typing.
    dept_input.fill("dws")

    # Trigger blur to fetch roles
    dept_input.blur()

    # Wait for role dropdown to appear
    role_group = page.locator("#role_group")
    expect(role_group).to_be_visible()

    # Select role
    role_select = page.locator("#role_id")
    role_select.select_option("admin.dws")

    # Wait for User ID input
    user_group = page.locator("#user_group")
    expect(user_group).to_be_visible()

    # Check User ID Label
    user_label = page.locator("label[for='user_id']")
    expect(user_label).to_have_text("User ID (Prefix Only)")

    # Check Placeholder
    user_input = page.locator("#user_id")
    expect(user_input).to_have_attribute("placeholder", "e.g., For 'anish.admin.dws', just type 'anish'")

    # Type user prefix
    user_input.fill("anish")

    # Verify Password field appears
    password_group = page.locator("#password_group")
    expect(password_group).to_be_visible()

    # Take screenshot
    page.screenshot(path="verification_frontend.png")

    browser.close()

with sync_playwright() as playwright:
    run(playwright)
