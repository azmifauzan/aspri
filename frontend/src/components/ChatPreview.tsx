import ChatBubble from "@/components/ChatBubble";
import { useTranslation } from "react-i18next";

export default function ChatPreview() {
  const { t } = useTranslation();
  return (
    <section className="
        py-20 px-4
        bg-zinc-100 dark:bg-zinc-900
        bg-gradient-to-b from-zinc-100/70 to-zinc-100
        dark:bg-gradient-to-b dark:from-zinc-800 dark:to-zinc-900
      " id="chat">
      <div className="max-w-3xl mx-auto">
        <h2 className="text-3xl md:text-4xl font-bold text-center mb-12 dark:text-zinc-100">
          {t("chatpreview.title")}
        </h2>

        <div className="rounded-xl shadow-lg bg-zinc-50 p-6 space-y-4">
          <ChatBubble
            side="right"
            text={t("chatpreview.user1")}
          />
          <ChatBubble
            side="left"
            text={t("chatpreview.aspri1")}
          />
          <ChatBubble
            side="right"
            text={t("chatpreview.user2")}
          />
          <ChatBubble
            side="left"
            text={t("chatpreview.aspri2")}
          />
          <ChatBubble
            side="right"
            text={t("chatpreview.user3")}
          />
          <ChatBubble
            side="left"
            text={t("chatpreview.aspri3")}
          />
        </div>
      </div>
    </section>
  );
}
