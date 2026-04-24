import { type ClassValue, clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs));
}

export const severityColors = {
  critical: 'bg-red-100 text-red-800 border-red-200',
  high: 'bg-orange-100 text-orange-800 border-orange-200',
  medium: 'bg-yellow-100 text-yellow-800 border-yellow-200',
  low: 'bg-blue-100 text-blue-800 border-blue-200',
};

export const statusColors = {
  open: 'bg-red-100 text-red-800 border-red-200',
  investigating: 'bg-amber-100 text-amber-800 border-amber-200',
  resolved: 'bg-green-100 text-green-800 border-green-200',
};

export const logLevelColors = {
  error: 'bg-red-100 text-red-800 border-red-200',
  warn: 'bg-yellow-100 text-yellow-800 border-yellow-200',
  info: 'bg-blue-100 text-blue-800 border-blue-200',
  debug: 'bg-gray-100 text-gray-800 border-gray-200',
};

export const environmentColors = {
  production: 'bg-red-100 text-red-800 border-red-200',
  staging: 'bg-amber-100 text-amber-800 border-amber-200',
  development: 'bg-green-100 text-green-800 border-green-200',
};

export const typeColors = {
  database: 'bg-purple-100 text-purple-800 border-purple-200',
  auth: 'bg-blue-100 text-blue-800 border-blue-200',
  network: 'bg-cyan-100 text-cyan-800 border-cyan-200',
  system: 'bg-gray-100 text-gray-800 border-gray-200',
  general: 'bg-slate-100 text-slate-800 border-slate-200',
};

export function formatDate(date: string): string {
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

export function formatRelativeTime(date: string): string {
  const now = new Date();
  const then = new Date(date);
  const diffMs = now.getTime() - then.getTime();
  const diffMins = Math.floor(diffMs / 60000);
  const diffHours = Math.floor(diffMs / 3600000);
  const diffDays = Math.floor(diffMs / 86400000);

  if (diffMins < 1) return formatDate(date);
  if (diffMins < 60) return `${diffMins}m ago (${formatDate(date)})`;
  if (diffHours < 24) return `${diffHours}h ago (${formatDate(date)})`;
  if (diffDays < 7) return `${diffDays}d ago (${formatDate(date)})`;
  return formatDate(date);
}

export function maskApiKey(key: string): string {
  if (key.length <= 12) return key;
  return `${key.substring(0, 8)}...${key.substring(key.length - 4)}`;
}

export function capitalize(str: string): string {
  return str.charAt(0).toUpperCase() + str.slice(1);
}