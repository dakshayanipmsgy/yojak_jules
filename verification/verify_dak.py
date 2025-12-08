
from playwright.sync_api import sync_playwright

def verify_dak_frontend():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        context = browser.new_context()
        page = context.new_page()

        # Login
        page.goto("http://localhost:8000/index.php")
        page.fill('input[name="user_id"]', "user1")
        page.fill('input[name="password"]', "password")
        page.fill('input[name="dept_id"]', "TEST_DEPT")
        page.click('button[type="submit"]')

        # Navigate to Dak Service
        page.goto("http://localhost:8000/dak_service.php")

        # Select Outgoing
        page.select_option('select[id="dak_type"]', 'outgoing')

        # Verify Internal Option selected by default (or explicitly select)
        # Note: In my code, I didn't set a default checked for radio buttons, wait, I did:
        # <input type="radio" name="destination_type" value="external" checked onchange="toggleFields()">
        # So External is default.

        # Switch to Internal
        page.click('input[value="internal"]')

        # Verify Internal Fields visible
        if not page.is_visible('#internal_recipient_group'):
            print("Internal Recipient Group not visible")
        if page.is_visible('#external_recipient_group'):
            print("External Recipient Group visible when it should not be")

        page.screenshot(path="verification/internal_mode.png")

        # Switch to External
        page.click('input[value="external"]')

        # Verify External Fields visible
        if not page.is_visible('#external_recipient_group'):
            print("External Recipient Group not visible")
        if not page.is_visible('#address_group'):
            print("Address Group not visible")
        if page.is_visible('#internal_recipient_group'):
            print("Internal Recipient Group visible when it should not be")

        page.screenshot(path="verification/external_mode.png")

        browser.close()

if __name__ == "__main__":
    verify_dak_frontend()
