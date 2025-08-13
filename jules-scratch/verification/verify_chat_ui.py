import asyncio
from playwright.async_api import async_playwright, expect

async def main():
    async with async_playwright() as p:
        browser = await p.chromium.launch(headless=True)
        context = await browser.new_context()
        page = await context.new_page()

        try:
            # Navigate to the base URL
            await page.goto("http://localhost:5173")

            # Inject a dummy user and token into local storage to bypass login
            dummy_user = {
                "id": 1,
                "name": "Test User",
                "email": "test@example.com"
            }
            dummy_token = "dummy.jwt.token"

            await page.evaluate(f'''() => {{
                localStorage.setItem('user', JSON.stringify({dummy_user}));
                localStorage.setItem('token', '{dummy_token}');
            }}''')

            # Navigate to the chat page directly
            await page.goto("http://localhost:5173/chat")

            # Take a screenshot to see what page we are on
            await page.screenshot(path="jules-scratch/verification/debug_screenshot.png")
            print("Took a debug screenshot to check the current page.")

            # Wait for the chat input to be visible
            chat_input = page.get_by_label("Chat input")
            await expect(chat_input).to_be_visible(timeout=10000)

            # Take a screenshot to verify the UI alignment
            await page.screenshot(path="jules-scratch/verification/chat_ui_alignment.png")
            print("Screenshot taken of the initial UI.")

            # Type a message and verify focus
            await chat_input.fill("This is a test message.")
            await page.get_by_role("button", name="Send").click()

            # The focus should return to the input. We can check if the input is focused.
            await expect(chat_input).to_be_focused(timeout=5000)
            print("Focus returned to input after sending message.")

            # Take a final screenshot
            await page.screenshot(path="jules-scratch/verification/final_chat_ui.png")
            print("Final screenshot taken.")

        except Exception as e:
            print(f"An error occurred: {e}")
            # Take a screenshot on error for debugging
            await page.screenshot(path="jules-scratch/verification/error.png")

        finally:
            await browser.close()

if __name__ == "__main__":
    asyncio.run(main())
