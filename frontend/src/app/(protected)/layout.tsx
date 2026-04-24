'use client';

import { AppLayout } from '@/components/layout';
import { ProtectedRoute } from '@/components/Providers';

export default function ProtectedLayout({ children }: { children: React.ReactNode }) {
  return (
    <ProtectedRoute>
      <AppLayout>{children}</AppLayout>
    </ProtectedRoute>
  );
}