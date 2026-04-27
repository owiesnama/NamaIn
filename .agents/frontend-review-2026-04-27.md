# Frontend Architecture Review — NamaIn (2026-04-27)

## Executive Summary

Reviewed 379 Vue and Blade files. The codebase demonstrates **strong overall design system compliance** with the Elmathani Design System. Overall grade: **B+**.

**Totals**: 0 P0, 49 P1, 19 P2 issues.

---

## Findings by Category

### 1. Design System Compliance

#### 1.1 Radius & Shadow — COMPLIANT

- 672 radius occurrences across 91 files, all using standard Tailwind classes
- 109 shadow occurrences across 53 files, all approved utilities

#### 1.2 Color/Purple Violations — P1

| File | Lines | Violation | Should Be |
|------|-------|-----------|-----------|
| `resources/js/Pages/Users/Index.vue` | 126 | `bg-purple-50 text-purple-700 border-purple-200 dark:bg-purple-900/20 dark:text-purple-400 dark:border-purple-800` | Use approved semantic color for role badges |
| `resources/js/Pages/Users/Index.vue` | 127 | `bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-900/20` | Use amber or another approved color |
| `resources/js/Pages/Products/Show.vue` | 265-268 | `bg-purple-50 dark:bg-purple-900/30` with `text-purple-600 dark:text-purple-400` | Replace purple with approved palette |

**Fix**: Remap role colors — Owner: emerald, Manager: amber (not blue), Staff: gray.

---

### 2. RTL/Arabic Layout

#### 2.1 Text Direction — COMPLIANT

Excellent RTL support. `text-start` used consistently, `dir` attribute set correctly in AppLayout.vue.

#### 2.2 Margin & Padding Direction — P1 (8 files)

Good patterns exist (`ltr:mr-4 rtl:ml-4`) but 8 files have remaining violations:

| File | Issue |
|------|-------|
| `resources/js/Pages/Sales/Create.vue` | Direct `pl-*`/`pr-*` without RTL pair |
| `resources/js/Pages/Purchases/Create.vue` | Directional spacing on form inputs |
| `resources/js/Shared/Cheque.vue` | Inconsistent icon spacing |
| `resources/js/Shared/FilterSidebar.vue` | Filter section padding without RTL variant |
| `resources/js/Components/GlobalSearch.vue` | Mixed icon positioning approach |

**Recommendation**: Replace `pl-*`/`pr-*` with logical properties (`ps-*`/`pe-*`) or `ltr:/rtl:` pairs.

---

### 3. Component Reuse & Architecture

#### 3.1 Shared Components — GOOD

40+ shared components, well-organized by domain. Structure:
```
Components/
  PrimaryButton.vue, SecondaryButton.vue, DangerButton.vue
  TextInput.vue, Modal.vue, DialogModal.vue
  Customers/, Products/, Suppliers/, Storages/, Purchases/
```

#### 3.2 Component Dark Mode Gaps — P1 (3 components)

| Component | Issue | Fix |
|-----------|-------|-----|
| `TextInput.vue` | Missing `dark:bg-gray-900 dark:text-white dark:border-gray-700` | Add dark variants |
| `InputLabel.vue` | Missing `dark:text-gray-300` | Add dark text color |
| `PrimaryButton.vue` | Missing `dark:focus:ring-offset-0` | Add dark focus ring offset |

#### 3.3 Duplication — P2

- 20+ form pages duplicate validation/error display logic
- Modal patterns repeated 15+ times
- **Recommendation**: Create reusable `FormInput.vue` and `FormError.vue` wrapper components

---

### 4. Accessibility

#### 4.1 Missing ARIA Labels — P1 (~25 elements)

| Context | Count | Example Files |
|---------|-------|---------------|
| Icon-only buttons | 25+ | Close/delete buttons throughout |
| Toggles/Checkboxes | 5+ | Dashboard.vue line 127 (privacy toggle) |
| Search inputs | 3+ | Users/Index, GlobalSearch |

**Priority fixes** (10 high-impact files):
1. Dashboard.vue — Privacy toggle
2. Users/Index.vue — Edit/Delete/Remove buttons (lines 286-305)
3. Products/Show.vue — Modal triggers
4. GlobalSearch.vue — Clear button
5. All delete confirmation buttons

#### 4.2 Form Labels — COMPLIANT

Labels correctly associated with inputs throughout.

#### 4.3 Color Contrast — COMPLIANT

All design system colors meet WCAG AA standards.

---

### 5. Performance

#### 5.1 Large Component Files — P2

| File | Lines | Recommendation |
|------|-------|----------------|
| `Pages/Users/Index.vue` | 685 | Split into sub-components |
| `Pages/Products/Show.vue` | 503 | Extract chart, stats sections |
| `Layouts/AppLayout.vue` | 380+ | Approaching limit |
| `Pages/Dashboard.vue` | 450+ | Extract chart/card sections |

#### 5.2 Watchers & Computed — GOOD

Proper debounce on filter watches, correct use of `computed()`.

#### 5.3 Inline Styles — P2 (1 violation)

`Products/Show.vue` lines 425, 434: inline `:style="{ width: ... }"` for progress bars. Should use computed properties.

---

### 6. Dark Mode — EXCELLENT (94% coverage)

Dark variants applied consistently across pages. Only 3 base components (TextInput, InputLabel, PrimaryButton) need minor additions.

---

### 7. Consistency — GOOD

- Naming conventions: PascalCase for components, camelCase for props/events
- Minor inconsistency: some components use `modelValue`, others use `value`
- **Recommendation**: Standardize on `modelValue` for v-model compatibility

---

## Priority Fix List

### Immediate (P1)

1. **Color palette violations** (3 files) — Replace purple/blue with approved semantic colors
2. **ARIA labels** (25+ elements, 10+ files) — Add to all icon-only buttons and toggles
3. **RTL consistency** (8 files) — Standardize directional spacing
4. **Component dark mode** (3 components) — TextInput, InputLabel, PrimaryButton

### Short-term (P2)

5. **Component extraction** — Extract modals, create shared StatsCard/FormInput wrappers
6. **Component consistency** — Standardize `modelValue`, add dark mode focus ring offsets
7. **Performance** — Replace inline width calculations with computed properties, split large pages

---

## Recommendations Going Forward

1. Add ESLint rules to catch `text-left`/`text-right`, validate color palette (no purple)
2. Test all pages in both LTR and RTL modes
3. Add dark mode visual regression tests
4. Create shared pattern library for modals, tables, filters
5. Audit and add ARIA labels to all interactive elements
