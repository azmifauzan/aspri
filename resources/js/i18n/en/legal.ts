export default {
    privacyPolicy: 'Privacy Policy',
    termsOfService: 'Terms of Service',
    lastUpdated: 'Last Updated: {date}',
    privacyContent: `
        <h2 class="text-2xl font-bold mt-6 mb-4">1. Introduction</h2>
        <p class="mb-4">Welcome to ASPRI (AI-Based Personal Assistant). ASPRI is a chat-first personal assistant that helps you manage schedules, finances, and notes through natural conversation on the Web and Telegram. We respect your privacy and are committed to protecting your personal data. This policy explains what data we collect, how we use it, and the choices you have.</p>

        <h2 class="text-2xl font-bold mt-6 mb-4">2. Data We Collect</h2>
        <p class="mb-4">We collect the following categories of data:</p>
        <ul class="list-disc pl-6 mb-4 space-y-2">
            <li><strong>Account data</strong> — your name, email address, and password (stored in hashed form). If you sign in with Google, we receive your Google account ID, email address, and profile picture.</li>
            <li><strong>Profile preferences</strong> — how you wish to be addressed, your assistant's name and persona, and your language preference.</li>
            <li><strong>Conversation data</strong> — messages you exchange with the assistant on the web chat and via the Telegram bot, organized into chat threads.</li>
            <li><strong>Conversation memories</strong> — key facts and preferences automatically extracted by AI from your conversations to provide continuity across sessions (e.g., your habits, recurring preferences).</li>
            <li><strong>Financial data</strong> — transactions, accounts (cash, bank, e-wallet), categories, budgets, and payment proof images you upload.</li>
            <li><strong>Schedule data</strong> — calendar events, recurring schedules, and reminders.</li>
            <li><strong>Notes</strong> — the content of notes you create, including tags and formatting.</li>
            <li><strong>Plugin data</strong> — data you record through optional plugins you activate (e.g., habit tracking, health tracking, mood journal, book tracking).</li>
            <li><strong>Integration data</strong> — your Telegram chat ID when you link your Telegram account.</li>
            <li><strong>Usage data</strong> — chat usage metering (token counts), activity logs, and subscription status, used for service operation and security auditing.</li>
        </ul>

        <h2 class="text-2xl font-bold mt-6 mb-4">3. How We Use Your Data</h2>
        <p class="mb-4">We use your data to:</p>
        <ul class="list-disc pl-6 mb-4 space-y-2">
            <li>Provide and personalize the assistant experience, including your chosen persona, name preference, and language.</li>
            <li>Process your requests (creating transactions, schedules, notes, and reminders) through AI intent recognition.</li>
            <li>Maintain conversation continuity through the memory system, so the assistant remembers relevant context across sessions.</li>
            <li>Send schedule reminders through in-app notifications and Telegram.</li>
            <li>Manage your subscription, including trial periods, payment verification, and promo code redemption.</li>
            <li>Monitor system health, prevent abuse, and maintain security audit trails.</li>
        </ul>

        <h2 class="text-2xl font-bold mt-6 mb-4">4. AI Processing and Third-Party Services</h2>
        <p class="mb-4">To generate responses, your conversation content (including relevant memory context) is processed by third-party AI providers configured for the service, which may include <strong>Google Gemini</strong>, <strong>OpenAI</strong>, and <strong>Anthropic Claude</strong>. These providers process your messages to produce assistant responses and intent analysis.</p>
        <p class="mb-4">We also integrate with:</p>
        <ul class="list-disc pl-6 mb-4 space-y-2">
            <li><strong>Google</strong> — for optional sign-in (OAuth). We only receive your basic profile information; we never see your Google password.</li>
            <li><strong>Telegram</strong> — for optional bot access. Messages sent via Telegram are processed the same way as web chat messages.</li>
        </ul>
        <p class="mb-4">We do not sell your personal data to third parties.</p>

        <h2 class="text-2xl font-bold mt-6 mb-4">5. Conversation Memory System</h2>
        <p class="mb-4">ASPRI automatically extracts important facts from your conversations (such as preferences and recurring topics) and stores them as memories to improve future interactions. Memories are periodically compacted, with the most important information preserved. Memories are only used to build context for your own conversations and are never shared with other users.</p>

        <h2 class="text-2xl font-bold mt-6 mb-4">6. Data Storage and Security</h2>
        <ul class="list-disc pl-6 mb-4 space-y-2">
            <li>Passwords are stored using one-way hashing and are never readable by us.</li>
            <li>Sensitive credentials (such as AI provider API keys) are stored encrypted.</li>
            <li>Two-factor authentication (TOTP) is available to protect your account.</li>
            <li>Administrative actions are recorded in audit logs.</li>
            <li>Accounts created via Google sign-in have password reset disabled until a password is set, preventing account takeover.</li>
        </ul>

        <h2 class="text-2xl font-bold mt-6 mb-4">7. Data Retention and Deletion</h2>
        <p class="mb-4">Your data is retained for as long as your account is active. You may delete individual items (transactions, schedules, notes, chat threads) at any time. When your account is deleted, your personal data — including conversations, memories, financial records, and uploaded files — is removed from our systems.</p>

        <h2 class="text-2xl font-bold mt-6 mb-4">8. Your Rights</h2>
        <p class="mb-4">You have the right to:</p>
        <ul class="list-disc pl-6 mb-4 space-y-2">
            <li>Access and update your account and profile data at any time through the settings page.</li>
            <li>Delete your conversations, records, and account.</li>
            <li>Deactivate plugins and stop their data collection.</li>
            <li>Unlink your Telegram account.</li>
            <li>Request a copy of your data by contacting us.</li>
        </ul>

        <h2 class="text-2xl font-bold mt-6 mb-4">9. Changes to This Policy</h2>
        <p class="mb-4">We may update this Privacy Policy from time to time. Material changes will be announced through the application. The "Last Updated" date at the top of this page reflects the latest revision.</p>

        <h2 class="text-2xl font-bold mt-6 mb-4">10. Contact Us</h2>
        <p class="mb-4">If you have any questions about this Privacy Policy or how your data is handled, please contact us through the application or via the contact details on our website.</p>
    `,
    termsContent: `
        <h2 class="text-2xl font-bold mt-6 mb-4">1. Acceptance of Terms</h2>
        <p class="mb-4">By accessing or using ASPRI, you agree to be bound by these Terms of Service and our Privacy Policy. If you do not agree, please do not use the service.</p>

        <h2 class="text-2xl font-bold mt-6 mb-4">2. Description of Service</h2>
        <p class="mb-4">ASPRI is an AI-powered personal assistant with a chat-first experience. The service includes:</p>
        <ul class="list-disc pl-6 mb-4 space-y-2">
            <li>Conversational AI assistant accessible via web chat and Telegram bot.</li>
            <li>Financial management — transactions, multiple accounts, categories, and budget tracking.</li>
            <li>Schedule management — calendar events, recurring schedules, and reminders.</li>
            <li>Notes with a rich block-based editor.</li>
            <li>An optional plugin system (e.g., habit tracker, prayer times, weather, currency converter).</li>
            <li>Cross-session conversation memory for a personalized experience.</li>
        </ul>

        <h2 class="text-2xl font-bold mt-6 mb-4">3. Accounts and Registration</h2>
        <ul class="list-disc pl-6 mb-4 space-y-2">
            <li>You must provide accurate information when registering, whether via email or Google sign-in.</li>
            <li>You are responsible for maintaining the confidentiality of your account credentials and for all activities under your account.</li>
            <li>We recommend enabling two-factor authentication for additional security.</li>
            <li>One person may not maintain multiple accounts to abuse trial periods or promotions.</li>
        </ul>

        <h2 class="text-2xl font-bold mt-6 mb-4">4. Subscription and Payment</h2>
        <ul class="list-disc pl-6 mb-4 space-y-2">
            <li>New accounts receive a free trial period with access to core features.</li>
            <li>Premium subscriptions are activated after payment proof is uploaded and verified by our team. Verification is performed manually and may take some time.</li>
            <li>Promo codes may be redeemed subject to their individual conditions and expiry.</li>
            <li>Chat usage may be metered (based on AI token usage) according to your subscription tier.</li>
            <li>We reserve the right to adjust pricing and plan features with prior notice through the application.</li>
        </ul>

        <h2 class="text-2xl font-bold mt-6 mb-4">5. AI-Generated Content Disclaimer</h2>
        <p class="mb-4">ASPRI uses artificial intelligence to understand your requests and generate responses. You acknowledge that:</p>
        <ul class="list-disc pl-6 mb-4 space-y-2">
            <li>AI-generated responses may occasionally be inaccurate, incomplete, or misinterpret your intent.</li>
            <li>Financial summaries, insights, and assistant suggestions are informational only and do <strong>not</strong> constitute financial, legal, medical, or professional advice.</li>
            <li>Data-changing actions (creating, updating, or deleting records) require your explicit confirmation before being executed. You are responsible for reviewing confirmations before approving them.</li>
            <li>You should verify important information independently before acting on it.</li>
        </ul>

        <h2 class="text-2xl font-bold mt-6 mb-4">6. Acceptable Use</h2>
        <p class="mb-4">You agree not to:</p>
        <ul class="list-disc pl-6 mb-4 space-y-2">
            <li>Use the service for unlawful purposes or to store unlawful content.</li>
            <li>Attempt to bypass usage limits, subscription restrictions, or security measures.</li>
            <li>Probe, disrupt, or overload the service infrastructure.</li>
            <li>Use the AI assistant to generate harmful, abusive, or deceptive content.</li>
            <li>Upload fraudulent payment proofs.</li>
        </ul>

        <h2 class="text-2xl font-bold mt-6 mb-4">7. Plugins and Third-Party Data</h2>
        <p class="mb-4">Plugins are optional features you may activate per account. Some plugins display data from third-party sources (e.g., weather, news headlines, prayer times, currency rates). Such data is provided "as is" and we do not guarantee its accuracy or availability.</p>

        <h2 class="text-2xl font-bold mt-6 mb-4">8. Telegram Integration</h2>
        <p class="mb-4">You may link your Telegram account using a one-time code to chat with ASPRI via Telegram. You are responsible for the security of your Telegram account; anyone with access to your linked Telegram account can interact with your ASPRI data. You may unlink Telegram at any time.</p>

        <h2 class="text-2xl font-bold mt-6 mb-4">9. Intellectual Property</h2>
        <p class="mb-4">The ASPRI application, including its design, branding, and software, is our intellectual property. Content you create (notes, records, conversations) remains yours; you grant us a limited license to store and process it solely to operate the service.</p>

        <h2 class="text-2xl font-bold mt-6 mb-4">10. Limitation of Liability</h2>
        <p class="mb-4">The service is provided "as is" and "as available". To the maximum extent permitted by law, we are not liable for indirect, incidental, or consequential damages arising from your use of the service, including losses resulting from reliance on AI-generated content, missed reminders, or service interruptions.</p>

        <h2 class="text-2xl font-bold mt-6 mb-4">11. Suspension and Termination</h2>
        <p class="mb-4">We may suspend or deactivate accounts that violate these terms, abuse the service, or submit fraudulent payments. You may stop using the service and delete your account at any time.</p>

        <h2 class="text-2xl font-bold mt-6 mb-4">12. Modifications to Terms</h2>
        <p class="mb-4">We reserve the right to modify these terms at any time. Material changes will be announced through the application. Your continued use of the service after changes take effect constitutes acceptance of the revised terms.</p>

        <h2 class="text-2xl font-bold mt-6 mb-4">13. Governing Law</h2>
        <p class="mb-4">These terms are governed by the laws of the Republic of Indonesia. Any disputes will be resolved through deliberation first, and through the competent courts if necessary.</p>
    `,
    backToHome: 'Back to Home',
};
