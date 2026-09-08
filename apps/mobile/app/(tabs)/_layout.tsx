import { Tabs } from 'expo-router';
import { Platform, Text, View } from 'react-native';

import { colors, typography } from '../../lib/theme';

/**
 * Bottom-tab layout. We render the "icon" as a coloured pill of the first
 * letter so we don't have to ship a vector-icons dep just to scaffold —
 * swap in `@expo/vector-icons` when you're ready.
 */
export default function TabsLayout() {
  return (
    <Tabs
      screenOptions={{
        tabBarActiveTintColor: colors.primary,
        tabBarInactiveTintColor: colors.textSubtle,
        tabBarStyle: {
          borderTopColor: colors.border,
          borderTopWidth: 0.5,
          height: Platform.OS === 'ios' ? 84 : 64,
          paddingBottom: Platform.OS === 'ios' ? 24 : 8,
          paddingTop: 6,
          backgroundColor: colors.surface,
        },
        tabBarLabelStyle: { ...typography.caption },
        headerStyle: {
          backgroundColor: colors.surface,
          borderBottomColor: colors.border,
        },
        headerTitleStyle: { ...typography.h3, color: colors.text },
        headerTitleAlign: 'center',
      }}
    >
      <Tabs.Screen
        name="index"
        options={{
          title: 'الرئيسية',
          tabBarIcon: ({ color }) => <Pill letter="ر" color={color} />,
        }}
      />
      <Tabs.Screen
        name="tasks"
        options={{
          title: 'المهام',
          tabBarIcon: ({ color }) => <Pill letter="م" color={color} />,
        }}
      />
      <Tabs.Screen
        name="leaves"
        options={{
          title: 'الإجازات',
          tabBarIcon: ({ color }) => <Pill letter="إ" color={color} />,
        }}
      />
      <Tabs.Screen
        name="profile"
        options={{
          title: 'الملف الشخصي',
          tabBarIcon: ({ color }) => <Pill letter="ح" color={color} />,
        }}
      />
    </Tabs>
  );
}

function Pill({ letter, color }: { letter: string; color: string }) {
  return (
    <View
      style={{
        width: 28,
        height: 28,
        borderRadius: 14,
        alignItems: 'center',
        justifyContent: 'center',
        borderWidth: 1.5,
        borderColor: color,
      }}
    >
      <Text style={{ color, fontSize: 13, fontWeight: '700' }}>{letter}</Text>
    </View>
  );
}
