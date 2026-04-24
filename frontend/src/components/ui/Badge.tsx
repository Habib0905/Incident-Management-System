'use client';

import { cn, severityColors, statusColors, logLevelColors, environmentColors, typeColors, capitalize } from '@/lib/utils';

interface BadgeProps {
  children: React.ReactNode;
  className?: string;
}

export function Badge({ children, className }: BadgeProps) {
  return (
    <span
      className={cn(
        'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border',
        className
      )}
    >
      {children}
    </span>
  );
}

export function SeverityBadge({ severity }: { severity: string }) {
  return (
    <Badge className={cn(severityColors[severity as keyof typeof severityColors] || severityColors.low)}>
      {capitalize(severity)}
    </Badge>
  );
}

export function StatusBadge({ status }: { status: string }) {
  return (
    <Badge className={cn(statusColors[status as keyof typeof statusColors] || statusColors.open)}>
      {status === 'investigating' ? 'Investigating' : capitalize(status)}
    </Badge>
  );
}

export function LogLevelBadge({ level }: { level: string | null }) {
  if (!level) return <Badge className="bg-gray-100 text-gray-600">Unknown</Badge>;
  return (
    <Badge className={cn(logLevelColors[level as keyof typeof logLevelColors] || logLevelColors.info)}>
      {capitalize(level)}
    </Badge>
  );
}

export function EnvironmentBadge({ environment }: { environment: string }) {
  return (
    <Badge className={cn(environmentColors[environment as keyof typeof environmentColors] || environmentColors.development)}>
      {capitalize(environment)}
    </Badge>
  );
}

export function TypeBadge({ type }: { type: string }) {
  return (
    <Badge className={cn(typeColors[type as keyof typeof typeColors] || typeColors.general)}>
      {capitalize(type)}
    </Badge>
  );
}

export function UnreadBadge({ show }: { show: boolean }) {
  if (!show) return null;
  return (
    <span className="flex h-2 w-2 relative">
      <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
      <span className="relative inline-flex rounded-full h-2 w-2 bg-red-500"></span>
    </span>
  );
}

export function ActiveBadge({ isActive }: { isActive: boolean }) {
  return (
    <Badge className={isActive ? 'bg-green-100 text-green-800 border-green-200' : 'bg-gray-100 text-gray-600 border-gray-200'}>
      {isActive ? 'Active' : 'Inactive'}
    </Badge>
  );
}