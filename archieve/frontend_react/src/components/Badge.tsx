import type { ReactNode } from "react";

type BadgeProps = { icon: ReactNode; text: string };

export default function Badge({ icon, text }: BadgeProps) {
  return (
    <div className="flex items-center gap-2 px-4 py-2 bg-zinc-50 rounded-lg shadow-sm">
      {icon}
      <span className="text-sm font-medium">{text}</span>
    </div>
  );
}
