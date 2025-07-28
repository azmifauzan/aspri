import { ReactNode } from "react";

type FeatureCardProps = {
  icon: ReactNode;
  title: string;
  desc: string;
};

export default function FeatureCard({ icon, title, desc }: FeatureCardProps) {
  return (
    <div className="flex flex-col items-center text-center p-6 rounded-xl shadow-md hover:shadow-lg transition bg-white dark:bg-zinc-800">
      <div className="w-14 h-14 flex items-center justify-center rounded-full bg-brand/10 dark:bg-brand/20 mb-4 text-brand">
        {icon}
      </div>

      <h3 className="text-lg font-semibold mb-2 text-zinc-800 dark:text-zinc-100">
        {title}
      </h3>
      <p className="text-sm text-zinc-600 dark:text-zinc-300">{desc}</p>
    </div>
  );
}
