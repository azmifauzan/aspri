type ChatBubbleProps = {
  side: "left" | "right";    // posisi gelembung
  text: string;
};

export default function ChatBubble({ side, text }: ChatBubbleProps) {
  const isLeft = side === "left";

  return (
    <div className={`flex ${isLeft ? "justify-start" : "justify-end"}`}>
      <div
        className={`max-w-[70%] rounded-lg p-3 text-sm leading-relaxed shadow
          ${isLeft
            ? "bg-white text-zinc-800"
            : "bg-brand text-white"
          }`}
      >
        {text}
      </div>
    </div>
  );
}
