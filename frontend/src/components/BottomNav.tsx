import { BookOpen, Home, Info, Mail } from 'lucide-react';
import type { SectionName } from '../types';

interface NavItem {
  key: SectionName;
  label: string;
  icon: typeof Home;
}

const items: NavItem[] = [
  { key: 'home', label: 'Accueil', icon: Home },
  { key: 'blog', label: 'Blogue', icon: BookOpen },
  { key: 'about', label: 'À propos', icon: Info },
  { key: 'contact', label: 'Contact', icon: Mail }
];

interface BottomNavProps {
  active: SectionName;
  onNavigate: (section: SectionName) => void;
}

export function BottomNav({ active, onNavigate }: BottomNavProps) {
  return (
    <nav className="fixed inset-x-0 bottom-0 z-30 border-t border-leaf-100 bg-white/95 px-2 pb-[max(calc(env(safe-area-inset-bottom)+0.125rem),0.5rem)] pt-2 shadow-[0_-12px_30px_rgba(7,95,159,0.12)] backdrop-blur">
      <div className="mx-auto grid max-w-md grid-cols-4 gap-1">
        {items.map((item) => {
          const Icon = item.icon;
          const isActive = active === item.key;

          return (
            <button
              key={item.key}
              type="button"
              className={`flex min-h-14 flex-col items-center justify-center gap-1 rounded-lg text-xs font-semibold transition ${
                isActive ? 'bg-leaf-700 text-white' : 'text-leaf-700 hover:bg-leaf-50'
              }`}
              onClick={() => onNavigate(item.key)}
              aria-current={isActive ? 'page' : undefined}
            >
              <Icon aria-hidden="true" size={20} strokeWidth={2.2} />
              <span>{item.label}</span>
            </button>
          );
        })}
      </div>
    </nav>
  );
}
