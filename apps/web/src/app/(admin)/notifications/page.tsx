'use client';

// Delegates to the shared notifications inbox component so the same page
// renders inside either the admin or employee layout. See fix #5 in the
// Wave D audit — regular employees who clicked the sidebar link were
// being teleported into the admin shell here.
export { NotificationsPage as default } from '@/components/notifications/notifications-page';
