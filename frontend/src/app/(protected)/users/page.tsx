'use client';

import { useState } from 'react';
import { useUsers, useCreateUser, useDeleteUser, useUpdateUser } from '@/hooks';
import { useAuth } from '@/store/auth';
import {
  Card,
  CardHeader,
  CardTitle,
  CardContent,
  Button,
  Input,
  Select,
  EmptyState,
  LoadingSpinner,
  Alert,
} from '@/components/ui';
import { Plus, Trash2, User, Pencil, X, Check } from 'lucide-react';

export default function UsersPage() {
  const { isAdmin, user: currentUser } = useAuth();
  const { data: users, isLoading, isError } = useUsers();
  const createUser = useCreateUser();
  const deleteUser = useDeleteUser();
  const updateUser = useUpdateUser();

  const [showCreateForm, setShowCreateForm] = useState(false);
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [role, setRole] = useState('engineer');
  const [error, setError] = useState('');

  const [editingUserId, setEditingUserId] = useState<number | null>(null);
  const [editName, setEditName] = useState('');
  const [editEmail, setEditEmail] = useState('');
  const [editPassword, setEditPassword] = useState('');
  const [editRole, setEditRole] = useState('');

  async function handleCreate() {
    if (!name || !email || !password) {
      setError('All fields are required');
      return;
    }
    setError('');
    try {
      await createUser.mutateAsync({ name, email, password, role });
      setShowCreateForm(false);
      setName('');
      setEmail('');
      setPassword('');
      setRole('engineer');
    } catch (err: any) {
      setError(err.response?.data?.error || 'Failed to create user');
    }
  }

  async function handleDelete(id: number) {
    if (!confirm('Are you sure you want to delete this user?')) return;
    try {
      await deleteUser.mutateAsync(id);
    } catch (err: any) {
      alert(err.response?.data?.error || 'Failed to delete user');
    }
  }

  function startEdit(user: any) {
    setEditingUserId(user.id);
    setEditName(user.name);
    setEditEmail(user.email);
    setEditPassword('');
    setEditRole(user.role);
  }

  function cancelEdit() {
    setEditingUserId(null);
    setEditName('');
    setEditEmail('');
    setEditPassword('');
    setEditRole('');
  }

  async function handleUpdate(id: number) {
    if (!editName || !editEmail) {
      return;
    }
    const data: { name: string; email: string; password?: string; role: string } = {
      name: editName,
      email: editEmail,
      role: editRole,
    };
    if (editPassword) {
      data.password = editPassword;
    }
    try {
      await updateUser.mutateAsync({ id, data });
      cancelEdit();
    } catch (err: any) {
      alert(err.response?.data?.error || 'Failed to update user');
    }
  }

  if (!isAdmin) {
    return (
      <div className="p-8">
        <Alert variant="error" title="Access Denied">
          Only administrators can access user management.
        </Alert>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Users</h1>
          <p className="text-gray-500">Manage user accounts</p>
        </div>
        <Button onClick={() => setShowCreateForm(!showCreateForm)}>
          <Plus className="h-4 w-4" />
          Add User
        </Button>
      </div>

      {showCreateForm && (
        <Card>
          <CardHeader>
            <CardTitle>Create New User</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <Input
              label="Name"
              placeholder="Full name"
              value={name}
              onChange={(e) => setName(e.target.value)}
            />
            <Input
              label="Email"
              type="email"
              placeholder="email@example.com"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
            />
            <Input
              label="Password"
              type="password"
              placeholder="Minimum 6 characters"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
            />
            <Select
              label="Role"
              value={role}
              onChange={(e) => setRole(e.target.value)}
              options={[
                { value: 'engineer', label: 'Engineer' },
                { value: 'admin', label: 'Admin' },
              ]}
            />
            {error && <p className="text-sm text-red-600">{error}</p>}
            <div className="flex gap-2">
              <Button onClick={handleCreate} loading={createUser.isPending}>
                Create User
              </Button>
              <Button variant="ghost" onClick={() => setShowCreateForm(false)}>
                Cancel
              </Button>
            </div>
          </CardContent>
        </Card>
      )}

      <Card>
        <CardContent className="p-0">
          {isLoading ? (
            <LoadingSpinner />
          ) : isError ? (
            <div className="p-8 text-center text-red-600">Failed to load users</div>
          ) : !users || users.length === 0 ? (
            <EmptyState message="No users yet" />
          ) : (
            <div className="divide-y divide-gray-200">
              {users.map((user) => (
                <div key={user.id} className="p-4">
                  {editingUserId === user.id ? (
                    <div className="space-y-3">
                      <Input
                        label="Name"
                        value={editName}
                        onChange={(e) => setEditName(e.target.value)}
                      />
                      <Input
                        label="Email"
                        type="email"
                        value={editEmail}
                        onChange={(e) => setEditEmail(e.target.value)}
                      />
                      <Input
                        label="Password"
                        type="password"
                        placeholder="Leave blank to keep current"
                        value={editPassword}
                        onChange={(e) => setEditPassword(e.target.value)}
                      />
                      <Select
                        label="Role"
                        value={editRole}
                        onChange={(e) => setEditRole(e.target.value)}
                        options={[
                          { value: 'engineer', label: 'Engineer' },
                          { value: 'admin', label: 'Admin' },
                        ]}
                      />
                      <div className="flex gap-2">
                        <Button
                          onClick={() => handleUpdate(user.id)}
                          loading={updateUser.isPending}
                          size="sm"
                        >
                          <Check className="h-4 w-4" />
                          Save
                        </Button>
                        <Button variant="ghost" size="sm" onClick={cancelEdit}>
                          <X className="h-4 w-4" />
                          Cancel
                        </Button>
                      </div>
                    </div>
                  ) : (
                    <div className="flex items-center justify-between">
                      <div className="flex items-center gap-4">
                        <div className="p-2 bg-gray-100 rounded-full">
                          <User className="h-5 w-5 text-gray-600" />
                        </div>
                        <div>
                          <div className="flex items-center gap-2">
                            <h3 className="font-medium text-gray-900">{user.name}</h3>
                            <span
                              className={`text-xs px-2 py-0.5 rounded-full ${
                                user.role === 'admin'
                                  ? 'bg-purple-100 text-purple-700'
                                  : 'bg-blue-100 text-blue-700'
                              }`}
                            >
                              {user.role}
                            </span>
                          </div>
                          <p className="text-sm text-gray-500">{user.email}</p>
                        </div>
                      </div>
                      <div className="flex items-center gap-1">
                        <button
                          onClick={() => startEdit(user)}
                          className="p-2 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-md"
                          title="Edit user"
                        >
                          <Pencil className="h-4 w-4" />
                        </button>
                        {user.role !== 'admin' && (
                          <button
                            onClick={() => handleDelete(user.id)}
                            disabled={deleteUser.isPending}
                            className="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-md"
                            title="Delete user"
                          >
                            <Trash2 className="h-4 w-4" />
                          </button>
                        )}
                      </div>
                    </div>
                  )}
                </div>
              ))}
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
}
