/**
 * Notifications tab.
 *
 * Fetches:
 *   - GET /me/notifications              → list
 *   - GET /me/notifications/unread-count → header badge (parallel)
 *
 * Tapping a row marks it read (POST /me/notifications/{id}/read) and
 * invalidates both queries. If the row carries a `url` we currently leave
 * routing to a follow-up wave — the string is kept on the payload.
 *
 * The empty-state bell icon is drawn as inline SVG-style primitives via
 * plain <View>s so we don't pull in a vector-icons dep just for a bell.
 */

import { useMemo } from 'react';
import {
  ActivityIndicator,
  FlatList,
  Pressable,
  RefreshControl,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { Button } from '../../components/Button';
import { api, extractApiMessage } from '../../lib/api';
import { colors, radius, spacing, typography } from '../../lib/theme';

interface Notification {
  id: number;
  title?: string | null;
  body?: string | null;
  url?: string | null;
  icon?: string | null;
  meta?: Record<string, unknown> | null;
  read_at?: string | null;
  created_at?: string | null;
}

interface Paginated<T> {
  data: T[];
  meta?: unknown;
}

interface UnreadCountResponse {
  count?: number;
  data?: { count?: number };
}

const LIST_KEY = ['me', 'notifications'] as const;
const COUNT_KEY = ['me', 'notifications', 'unread-count'] as const;

export default function NotificationsScreen() {
  const queryClient = useQueryClient();

  const list = useQuery({
    queryKey: LIST_KEY,
    queryFn: async (): Promise<Notification[]> => {
      const res = await api.get<Paginated<Notification> | Notification[]>(
        '/me/notifications',
      );
      return Array.isArray(res.data) ? res.data : res.data.data;
    },
  });

  const unread = useQuery({
    queryKey: COUNT_KEY,
    queryFn: async (): Promise<number> => {
      const res = await api.get<UnreadCountResponse>('/me/notifications/unread-count');
      return res.data?.count ?? res.data?.data?.count ?? 0;
    },
  });

  const markOne = useMutation({
    mutationFn: (id: number) => api.post(`/me/notifications/${id}/read`),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: LIST_KEY });
      void queryClient.invalidateQueries({ queryKey: COUNT_KEY });
    },
  });

  const markAll = useMutation({
    mutationFn: () => api.post('/me/notifications/read-all'),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: LIST_KEY });
      void queryClient.invalidateQueries({ queryKey: COUNT_KEY });
    },
  });

  function onPressRow(item: Notification) {
    if (!item.read_at) {
      markOne.mutate(item.id);
    }
    // Deep-link routing wired in a later wave — payload preserved on `item.url`.
  }

  const unreadCount = unread.data ?? 0;

  return (
    <View style={styles.wrap}>
      <View style={styles.headerBar}>
        <View style={styles.headerLeft}>
          <Text style={styles.headerTitle}>الإشعارات</Text>
          {unreadCount > 0 ? (
            <View style={styles.badge}>
              <Text style={styles.badgeText}>{unreadCount}</Text>
            </View>
          ) : null}
        </View>
        {unreadCount > 0 ? (
          <Button
            label="تعليم الكل كمقروء"
            size="sm"
            variant="secondary"
            loading={markAll.isPending}
            onPress={() => markAll.mutate()}
          />
        ) : null}
      </View>

      {list.isLoading ? (
        <View style={styles.center}>
          <ActivityIndicator color={colors.primary} />
        </View>
      ) : list.isError ? (
        <View style={styles.center}>
          <Text style={styles.errorTitle}>تعذّر تحميل الإشعارات</Text>
          <Text style={styles.errorBody}>{extractApiMessage(list.error)}</Text>
        </View>
      ) : (
        <FlatList
          data={list.data ?? []}
          keyExtractor={(n) => String(n.id)}
          contentContainerStyle={styles.list}
          ItemSeparatorComponent={() => <View style={{ height: spacing.sm }} />}
          ListEmptyComponent={<Empty />}
          refreshControl={
            <RefreshControl
              refreshing={list.isRefetching}
              onRefresh={() => {
                void list.refetch();
                void unread.refetch();
              }}
              tintColor={colors.primary}
            />
          }
          renderItem={({ item }) => (
            <NotificationRow item={item} onPress={() => onPressRow(item)} />
          )}
        />
      )}
    </View>
  );
}

function NotificationRow({
  item,
  onPress,
}: {
  item: Notification;
  onPress: () => void;
}) {
  const unread = !item.read_at;
  const relative = useMemo(() => relativeArabic(item.created_at), [item.created_at]);

  return (
    <Pressable
      onPress={onPress}
      style={({ pressed }) => [
        styles.row,
        unread && styles.rowUnread,
        pressed && { opacity: 0.85 },
      ]}
    >
      {unread ? <View style={styles.dot} /> : null}
      <View style={styles.rowBody}>
        <Text style={styles.rowTitle} numberOfLines={2}>
          {item.title ?? 'إشعار'}
        </Text>
        {item.body ? (
          <Text style={styles.rowBodyText} numberOfLines={3}>
            {item.body}
          </Text>
        ) : null}
        {relative ? <Text style={styles.rowTime}>{relative}</Text> : null}
      </View>
    </Pressable>
  );
}

function Empty() {
  return (
    <View style={styles.center}>
      <BellIcon />
      <Text style={styles.emptyTitle}>لا توجد إشعارات</Text>
      <Text style={styles.emptyBody}>
        عند وصول إشعار جديد ستجده هنا.
      </Text>
    </View>
  );
}

/**
 * Inline bell "icon" — two rounded rectangles for the body + clapper, so
 * we can render it without a vector-icons dependency.
 */
function BellIcon() {
  return (
    <View style={styles.bellWrap}>
      <View style={styles.bellBody} />
      <View style={styles.bellRim} />
      <View style={styles.bellClapper} />
    </View>
  );
}

/**
 * Very small relative-time formatter — "منذ N د/س/ي".
 * We keep it inline so we don't pull in dayjs just for one string.
 */
function relativeArabic(iso?: string | null): string | null {
  if (!iso) return null;
  const then = new Date(iso).getTime();
  if (Number.isNaN(then)) return null;

  const diffSeconds = Math.max(0, Math.floor((Date.now() - then) / 1000));
  if (diffSeconds < 60) return 'الآن';

  const diffMinutes = Math.floor(diffSeconds / 60);
  if (diffMinutes < 60) return `منذ ${diffMinutes} د`;

  const diffHours = Math.floor(diffMinutes / 60);
  if (diffHours < 24) return `منذ ${diffHours} س`;

  const diffDays = Math.floor(diffHours / 24);
  if (diffDays < 30) return `منذ ${diffDays} ي`;

  const diffMonths = Math.floor(diffDays / 30);
  if (diffMonths < 12) return `منذ ${diffMonths} شهر`;

  const diffYears = Math.floor(diffMonths / 12);
  return `منذ ${diffYears} سنة`;
}

const styles = StyleSheet.create({
  wrap: { flex: 1 },
  headerBar: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: spacing.lg,
    paddingTop: spacing.lg,
    paddingBottom: spacing.sm,
    gap: spacing.md,
  },
  headerLeft: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
  },
  headerTitle: { ...typography.h2, color: colors.text },
  badge: {
    minWidth: 24,
    height: 24,
    paddingHorizontal: 6,
    borderRadius: radius.pill,
    backgroundColor: colors.primary,
    alignItems: 'center',
    justifyContent: 'center',
  },
  badgeText: {
    ...typography.caption,
    color: colors.textOnPrimary,
    fontWeight: '700',
  },
  list: { padding: spacing.lg, flexGrow: 1 },
  center: {
    flex: 1,
    padding: spacing.xl,
    alignItems: 'center',
    justifyContent: 'center',
    gap: spacing.sm,
  },
  errorTitle: { ...typography.h3, color: colors.danger },
  errorBody: { ...typography.small, color: colors.textMuted, textAlign: 'center' },

  row: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: spacing.sm,
    padding: spacing.md,
    borderRadius: radius.lg,
    backgroundColor: colors.surface,
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: colors.border,
  },
  rowUnread: {
    backgroundColor: colors.primaryLight,
    borderColor: colors.primaryLight,
  },
  dot: {
    width: 8,
    height: 8,
    borderRadius: 4,
    marginTop: 8,
    backgroundColor: colors.primary,
  },
  rowBody: { flex: 1, gap: spacing.xs },
  rowTitle: { ...typography.bodyStrong, color: colors.text },
  rowBodyText: { ...typography.small, color: colors.textMuted },
  rowTime: { ...typography.caption, color: colors.textSubtle, marginTop: spacing.xs },

  emptyTitle: { ...typography.h3, color: colors.text, marginTop: spacing.md },
  emptyBody: { ...typography.small, color: colors.textMuted, textAlign: 'center' },

  bellWrap: {
    width: 56,
    height: 56,
    alignItems: 'center',
    justifyContent: 'center',
    position: 'relative',
  },
  bellBody: {
    width: 34,
    height: 34,
    borderTopLeftRadius: 17,
    borderTopRightRadius: 17,
    borderBottomLeftRadius: 6,
    borderBottomRightRadius: 6,
    borderWidth: 2.5,
    borderColor: colors.textSubtle,
    backgroundColor: 'transparent',
  },
  bellRim: {
    position: 'absolute',
    bottom: 8,
    width: 44,
    height: 4,
    borderRadius: 2,
    backgroundColor: colors.textSubtle,
  },
  bellClapper: {
    position: 'absolute',
    bottom: 2,
    width: 10,
    height: 6,
    borderBottomLeftRadius: 5,
    borderBottomRightRadius: 5,
    backgroundColor: colors.textSubtle,
  },
});
