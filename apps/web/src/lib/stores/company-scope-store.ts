import { create } from 'zustand';
import { persist } from 'zustand/middleware';

/**
 * Soft Company Scoping — the admin-side "which company am I looking at
 * right now" filter.
 *
 * null = all companies (default / super-admin's global view). Any numeric
 * id scopes every list hook that reads this store to that company. The
 * value is persisted via localStorage under the key below so the
 * selection survives a page reload, and the store is the single source
 * of truth every list hook subscribes to — switching companies in the
 * header triggers an automatic React Query refetch because the id
 * becomes part of each hook's query key.
 *
 * Deliberately NOT part of auth state: logging in or out should not
 * forcibly clear the scope (the next admin on the same tab inherits the
 * previous selection, which matches how browsers persist most UI
 * preferences). An explicit "clear" happens only via `setScopedCompanyId(null)`.
 */
type CompanyScopeState = {
  scopedCompanyId: number | null;
  setScopedCompanyId: (id: number | null) => void;
};

export const useCompanyScopeStore = create<CompanyScopeState>()(
  persist(
    (set) => ({
      scopedCompanyId: null,
      setScopedCompanyId: (id) => set({ scopedCompanyId: id }),
    }),
    {
      name: 'taqat-company-scope',
      // Only persist the id — the setter is bound to this instance's
      // closure and must not be rehydrated from storage.
      partialize: (state) => ({ scopedCompanyId: state.scopedCompanyId }),
    }
  )
);

/**
 * Convenience hook for the common case: a list query wants the current
 * scope id as a plain value. Spelled out as its own hook so consumers
 * subscribe to the minimal slice (avoids re-render churn when the
 * setter identity changes, which `persist` can trigger on hydration).
 */
export function useScopedCompanyId(): number | null {
  return useCompanyScopeStore((s) => s.scopedCompanyId);
}
