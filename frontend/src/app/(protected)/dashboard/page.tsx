'use client';

import { useIncidents, useUnreadCount, useIncidentStats } from '@/hooks';
import { useAuth } from '@/store/auth';
import { Card, CardHeader, CardTitle, CardContent, SeverityBadge, StatusBadge, UnreadBadge } from '@/components/ui';
import { Clock, CheckCircle, AlertCircle, UserX, Bell, User } from 'lucide-react';
import Link from 'next/link';
import { formatRelativeTime } from '@/lib/utils';

function StatCard({
  title,
  value,
  icon: Icon,
  color,
  link,
}: {
  title: string;
  value: number;
  icon: React.ElementType;
  color: string;
  link?: string;
}) {
  const content = (
    <Card className="hover:shadow-md transition-shadow min-w-0">
      <CardContent className="flex items-center gap-3 p-4">
        <div className={`p-2.5 rounded-lg flex-shrink-0 ${color}`}>
          <Icon className="h-5 w-5 text-white" />
        </div>
        <div className="min-w-0 flex-1">
          <p className="text-xl font-bold text-gray-900 truncate">{value}</p>
          <p className="text-xs text-gray-500 truncate">{title}</p>
        </div>
      </CardContent>
    </Card>
  );

  if (link) {
    return <Link href={link}>{content}</Link>;
  }

  return content;
}

export default function DashboardPage() {
  const { user, isAdmin } = useAuth();
  const { data: incidents, isLoading } = useIncidents();
  const { data: stats, isLoading: statsLoading } = useIncidentStats();

  const recentIncidents = incidents?.incidents?.slice(0, 10) || [];

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Dashboard</h1>
          <p className="text-gray-500">Overview of incident management</p>
        </div>
      </div>

      <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
        <StatCard
          title="Open"
          value={stats?.open ?? 0}
          icon={AlertCircle}
          color="bg-red-500"
          link="/incidents?status=open"
        />
        <StatCard
          title="Investigating"
          value={stats?.investigating ?? 0}
          icon={Clock}
          color="bg-amber-500"
          link="/incidents?status=investigating"
        />
        <StatCard
          title="Resolved"
          value={stats?.resolved ?? 0}
          icon={CheckCircle}
          color="bg-green-500"
          link="/incidents?status=resolved"
        />
        {isAdmin ? (
          <StatCard
            title="Unassigned"
            value={stats?.unassigned ?? 0}
            icon={UserX}
            color="bg-gray-500"
            link="/incidents?filter=unassigned"
          />
        ) : (
          <StatCard
            title="Assigned to Me"
            value={stats?.assigned_to_me ?? 0}
            icon={User}
            color="bg-purple-500"
            link="/incidents?filter=assigned_to_me"
          />
        )}
        <StatCard
          title="Unread"
          value={stats?.unread ?? 0}
          icon={Bell}
          color="bg-blue-500"
          link="/incidents"
        />
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Recent Incidents</CardTitle>
        </CardHeader>
        <CardContent className="p-0">
          {isLoading || statsLoading ? (
            <div className="p-8 text-center text-gray-500">Loading...</div>
          ) : recentIncidents.length === 0 ? (
            <div className="p-8 text-center text-gray-500">No incidents found</div>
          ) : (
            <div className="divide-y divide-gray-200">
              {recentIncidents.map((incident) => (
                <Link
                  key={incident.id}
                  href={`/incidents/${incident.id}`}
                  className="flex items-center gap-4 p-4 hover:bg-gray-50 transition-colors"
                >
                  <UnreadBadge show={!incident.is_viewed} />
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2 mb-1">
                      <span className="font-medium text-gray-900 truncate">{incident.title}</span>
                      <SeverityBadge severity={incident.severity} />
                      <StatusBadge status={incident.status} />
                    </div>
                    <div className="flex items-center gap-4 text-sm text-gray-500">
                      {incident.server && (
                        <span className="truncate">{incident.server.name}</span>
                      )}
                      <span>{formatRelativeTime(incident.created_at)}</span>
                      {incident.assigned_user && (
                        <span>Assigned to {incident.assigned_user.name}</span>
                      )}
                    </div>
                  </div>
                </Link>
              ))}
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
}