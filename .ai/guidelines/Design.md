# UI Design Guidelines

This document is the single source of truth for UI generation in this project.
Any AI agent writing Vue/Tailwind code must produce output that is indistinguishable from the existing codebase.

---

## Stack

- **Framework**: Vue 3 + Inertia.js v1
- **Styling**: Tailwind CSS v3 (utility-first, no component library)
- **Icons**: Heroicons — always inline SVG, never an icon component or font
- **Dark mode**: Supported everywhere via `dark:` prefix
- **RTL**: Supported everywhere via `rtl:` / `ltr:` prefixes and logical properties

---
## Flat Design Philosophy

The agent must strictly follow a **flat design philosophy** across all generated UI.

### Core Principle
Interfaces must feel clean, modern, lightweight, and visually calm.  
Avoid decorative styling that adds visual noise without improving usability.

### Rules

1. **No skeuomorphic styling**
    - Do not imitate real-world materials such as glass, metal, paper, leather, or plastic.
    - No glossy surfaces, bevels, embossing, inset highlights, or realistic textures.

2. **Shadows must be minimal**
    - Use shadows only when necessary to clarify hierarchy or focus.
    - Prefer `shadow-sm` for standard surfaces.
    - Avoid heavy, layered, dramatic, or floating-card shadows unless explicitly required for modals or overlays.

3. **No gradients in the main application UI**
    - Use solid colors for surfaces, buttons, badges, borders, and states.
    - Gradients are not allowed in standard tenant/app pages.
    - If gradients are used in auth or marketing-like screens, they must remain subtle and intentional, never dominant.

4. **Rely on spacing, contrast, and borders instead of decoration**
    - Visual hierarchy should come from typography, layout, spacing, alignment, and restrained color usage.
    - Prefer clear borders, soft background contrast, and consistent structure over ornamental effects.

5. **Keep surfaces simple**
    - Cards, panels, tables, modals, and inputs should use flat fills with clean borders.
    - Avoid layered visual treatments that make components feel overly dimensional.

6. **Icons and illustrations must stay simple**
    - Use clean inline SVG icons only.
    - Avoid overly detailed, 3D-looking, filled decorative icons unless the surrounding pattern clearly calls for them.

7. **Interactive states must remain flat**
    - Hover, active, selected, and focus states should be expressed through:
        - slight color shifts
        - border/ring changes
        - subtle background changes
    - Avoid glow effects, exaggerated scaling, or flashy animation.

8. **Emphasis should come from color and typography, not visual effects**
    - Important actions should stand out because of strong layout placement, readable labels, and brand color usage.
    - Never rely on gradients, deep shadows, or excessive animation to create importance.

### Practical Guidance
When choosing between two UI treatments, always prefer the one that is:
- simpler
- flatter
- cleaner
- easier to scan
- less visually noisy

### Enforcement
Any generated component that introduces unnecessary visual depth, decorative gradients, glossy effects, or excessive shadow should be considered **non-compliant** with the design system.



## Color Palette

### Brand / Primary
| Role | Class | Usage |
|---|---|---|
| Primary action | `emerald-600` | Buttons, active nav, focus rings, links |
| Primary hover | `emerald-700` | Button hover state |
| Primary light | `emerald-50` | Badge backgrounds, active nav bg |
| Primary ring | `emerald-200` | Focus ring color |
| Primary muted | `emerald-400` | Dark mode accents |

### Neutrals (used for all text, borders, backgrounds)
| Shade | Usage |
|---|---|
| `gray-900` | Primary text (light mode), page background (dark mode) |
| `gray-800` | Secondary text, dark mode cards |
| `gray-700` | Form labels, secondary text |
| `gray-600` | Tertiary text, muted content |
| `gray-500` | Placeholder text, disabled states |
| `gray-400` | Table headers, icon color, borders (light) |
| `gray-300` | Borders (standard) |
| `gray-200` | Input borders, dividers |
| `gray-100` | Light backgrounds |
| `gray-50` | Table header background, lightest surfaces |

### Auth Pages Only
Auth pages (`Login.vue`, `Register.vue`, `Tenants/Select.vue`) use **slate** instead of gray:
`slate-900`, `slate-800`, `slate-700`, `slate-600`, `slate-500`, `slate-400`, `slate-200`, `slate-100`, `slate-50`

### Status Colors
| Status | Background | Text | Border | Dark bg | Dark text |
|---|---|---|---|---|---|
| Success / In Stock | `emerald-50` | `emerald-700` | `emerald-200` | `emerald-900/20` | `emerald-400` |
| Warning / Low Stock | `orange-50` | `orange-700` | `orange-200` | `orange-900/20` | `orange-400` |
| Danger / Out of Stock | `red-50` | `red-700` | `red-200` | `red-900/20` | `red-400` |
| Overcommitted | `amber-50` | `amber-700` | `amber-200` | `amber-900/20` | `amber-400` |
| Pending / Due Soon | `amber-100` | `amber-700` | — | `amber-900/30` | `amber-400` |
| Overdue | `red-100` | `red-700` | — | `red-900/30` | `red-400` |
| Neutral / Info | `gray-100` | `gray-600` | `gray-200` | `gray-800` | `gray-400` |

### Financial Amounts
- Amount owed (negative): `text-red-600 dark:text-red-400`
- Amount due to you (positive): `text-emerald-600 dark:text-emerald-400`
- Neutral / zero: `text-gray-600 dark:text-gray-400`

---

## Dark Mode

Every element must have a dark mode variant. Never write a background, text, or border color without its `dark:` pair.

| Light | Dark |
|---|---|
| `bg-white` | `dark:bg-gray-900` |
| `bg-gray-50` | `dark:bg-gray-800/40` |
| `bg-gray-100` | `dark:bg-gray-800` |
| `text-gray-900` | `dark:text-white` |
| `text-gray-700` | `dark:text-gray-300` |
| `text-gray-600` | `dark:text-gray-400` |
| `text-gray-500` | `dark:text-gray-500` |
| `text-gray-400` | `dark:text-gray-500` |
| `border-gray-200` | `dark:border-gray-700` |
| `border-gray-300` | `dark:border-gray-600` |
| `hover:bg-gray-50` | `dark:hover:bg-gray-800` |
| `divide-gray-200` | `dark:divide-gray-700` |

---

## Typography

| Use Case | Classes |
|---|---|
| Page title | `text-xl font-semibold text-gray-800 dark:text-white` |
| Section heading | `text-lg font-semibold text-gray-800 dark:text-white` |
| Card title | `text-base font-semibold text-gray-900 dark:text-white` |
| Table header | `text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500` |
| Form label | `block text-sm font-medium text-gray-700 dark:text-gray-300 rtl:text-right` |
| Body text | `text-sm text-gray-700 dark:text-gray-300` |
| Secondary text | `text-sm text-gray-500 dark:text-gray-400` |
| Caption / meta | `text-xs text-gray-400 dark:text-gray-500` |
| Micro label | `text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500` |
| Currency / amount | `text-sm font-semibold` (table) or `text-2xl font-bold` (dashboard hero) |
| Link | `text-emerald-600 dark:text-emerald-400 hover:text-emerald-700 dark:hover:text-emerald-300` |
| Error message | `text-sm text-red-600 dark:text-red-400` |

---

## Spacing

### Padding
| Context | Value |
|---|---|
| Card / panel | `p-6` |
| Section with border | `px-6 py-4` |
| Table cell | `px-6 py-4` |
| Form input | `px-3 py-2` |
| Button | `px-4 py-2` |
| Badge | `px-2.5 py-0.5` or `px-3 py-1` |
| Sidebar section | `p-5` |
| Filter section | `p-5` |
| List item | `px-5 py-3` or `px-5 py-4` |

### Gaps
- Between form fields (vertical): `space-y-4` or `space-y-6`
- Between sibling elements: `gap-3` or `gap-4`
- Between icon and text: `gap-x-2` or `gap-x-3`
- Between action buttons: `gap-x-4`
- Between grid columns: `gap-6`

### Margin
- Between page sections: `mb-6`
- Below page header: `mb-8`
- After form actions: `mt-4`

---

## Border Radius

| Element | Class |
|---|---|
| Cards, tables, panels | `rounded-lg` (0.5rem) |
| Buttons | `rounded-lg` |
| Inputs | `rounded-lg` |
| Badges / pills | `rounded-full` |
| Larger cards, modals | `rounded-xl` (0.75rem) |
| Auth page elements | `rounded-xl` or `rounded-2xl` |
| Icon containers | `rounded-xl` or `rounded-2xl` |
| Small tags | `rounded-md` |

---

## Shadows

- Cards / elevated panels: `shadow-sm`
- Modals: `shadow-xl`
- Auth icon containers: `shadow-lg shadow-emerald-200`
- Buttons (auth only): `shadow-md shadow-emerald-200`
- Standard buttons: no shadow

---

## Component Patterns

### Card / Panel

```html
<div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
  <!-- content -->
</div>
```

For sections with a header bar:
```html
<div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl">
  <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Title</h3>
  </div>
  <div class="p-6">
    <!-- content -->
  </div>
</div>
```

---

### Page Header

```html
<div class="w-full lg:flex lg:items-center lg:justify-between">
  <div>
    <div class="flex items-center gap-x-3">
      <h2 class="text-xl font-semibold text-gray-800 dark:text-white">Page Title</h2>
      <span class="px-3 py-1 text-xs font-semibold rounded-full text-emerald-700 bg-emerald-100/60 dark:bg-gray-800 dark:text-emerald-400">
        {{ count }} Items
      </span>
    </div>
    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Optional subtitle</p>
  </div>
  <div class="mt-4 flex items-center justify-end gap-x-4 lg:mt-0">
    <!-- action buttons -->
  </div>
</div>
```

---

### Buttons

**Primary:**
```html
<button class="inline-flex items-center justify-center px-4 py-2 text-sm font-normal text-white bg-emerald-600 border border-transparent rounded-lg hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-200">
  Action
</button>
```

**Secondary:**
```html
<button class="inline-flex items-center justify-center px-4 py-2 text-sm font-normal text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-200">
  Cancel
</button>
```

**Danger:**
```html
<button class="inline-flex items-center justify-center px-4 py-2 text-sm font-normal text-white bg-red-600 border border-transparent rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-200">
  Delete
</button>
```

**Icon-only (toolbar):**
```html
<button class="inline-flex items-center justify-center p-2.5 text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors duration-200">
  <svg class="h-4 w-4"><!-- icon --></svg>
</button>
```

**Auth page full-width gradient button:**
```html
<button class="w-full rounded-xl bg-gradient-to-l from-emerald-600 to-emerald-400 py-3.5 text-base font-semibold text-white shadow-md shadow-emerald-200 transition hover:from-emerald-500 hover:to-emerald-300 focus:outline-none focus:ring-2 focus:ring-emerald-400 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60">
  Submit
</button>
```

---

### Form Inputs

**Standard input:**
```html
<label class="block text-sm font-medium text-gray-700 dark:text-gray-300 rtl:text-right">
  Label
</label>
<input
  type="text"
  class="w-full px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50 placeholder-gray-400 dark:placeholder-gray-600"
/>
<p class="mt-1 text-sm text-red-600 dark:text-red-400">Error message</p>
```

**Auth page split-segment input (icon | input):**
```html
<div class="flex overflow-hidden rounded-xl border border-slate-200 bg-slate-100 transition focus-within:border-emerald-400 focus-within:bg-white focus-within:ring-2 focus-within:ring-emerald-100">
  <div class="flex shrink-0 items-center border-e border-slate-200 bg-slate-100 px-3.5">
    <svg class="h-4 w-4 text-slate-400"><!-- icon --></svg>
  </div>
  <input class="min-w-0 flex-1 bg-transparent border-0 px-4 py-3 text-sm text-slate-800 placeholder-slate-400 focus:outline-none" />
</div>
```

**Auth page split-segment with eye toggle (icon | input | toggle):**
```html
<div class="flex overflow-hidden rounded-xl border border-slate-200 bg-slate-100 transition focus-within:border-emerald-400 focus-within:bg-white focus-within:ring-2 focus-within:ring-emerald-100">
  <div class="flex shrink-0 items-center border-e border-slate-200 bg-slate-100 px-3.5">
    <svg class="h-4 w-4 text-slate-400"><!-- icon --></svg>
  </div>
  <input class="min-w-0 flex-1 bg-transparent border-0 px-4 py-3 text-sm text-slate-800 focus:outline-none" />
  <button type="button" class="flex shrink-0 items-center border-s border-slate-200 bg-slate-100 px-3.5 text-slate-400 transition hover:text-slate-600 focus:outline-none">
    <svg class="h-4 w-4"><!-- eye icon --></svg>
  </button>
</div>
```

**Checkbox:**
```html
<input type="checkbox" class="border-gray-300 dark:border-gray-600 rounded text-emerald-600 focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50" />
```

**Textarea:**
```html
<textarea class="w-full px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"></textarea>
```

---

### Tables

```html
<div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
  <div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
      <thead class="bg-gray-50/50 dark:bg-gray-800/40">
        <tr>
          <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">
            Column Header
          </th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-200/60 dark:divide-gray-700/60 bg-white dark:bg-gray-900">
        <tr class="group hover:bg-gray-50 dark:hover:bg-gray-800 transition-all duration-200">
          <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
            Cell content
          </td>
        </tr>
      </tbody>
    </table>
  </div>

  <!-- Empty state -->
  <div class="py-12 text-center text-sm text-gray-400 dark:text-gray-500">
    No records found.
  </div>
</div>
```

Table header uses `text-start` (logical property for RTL support), never `text-left`.

---

### Badges / Status Pills

**Rounded pill (count badge beside page title):**
```html
<span class="px-3 py-1 text-xs font-semibold rounded-full text-emerald-700 bg-emerald-100/60 dark:bg-gray-800 dark:text-emerald-400">
  42 Items
</span>
```

**Status badge with border:**
```html
<div class="inline-flex items-center gap-x-1.5 px-2.5 py-1 text-[11px] font-bold rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-700 dark:bg-emerald-900/20 dark:border-emerald-800 dark:text-emerald-400">
  In Stock
</div>
```

**Small category tag:**
```html
<span class="px-1.5 py-0.5 text-[9px] font-medium bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400 rounded-md">
  Category
</span>
```

---

### Sidebar Navigation

**Layout:**
```html
<aside class="fixed inset-y-0 start-0 w-64 bg-white dark:bg-gray-900 border-e border-gray-200 dark:border-gray-700 overflow-y-auto">
```

**Nav link (active and inactive states):**
```html
<a :class="[
  'flex items-center gap-x-3 px-3 py-2 rounded-lg transition-all duration-300',
  active
    ? 'text-emerald-700 bg-emerald-50 dark:bg-emerald-900/20 dark:text-emerald-400 font-semibold shadow-sm ring-1 ring-inset ring-emerald-500/10'
    : 'text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800/50 hover:text-emerald-600 dark:hover:text-emerald-400 hover:translate-x-1 rtl:hover:-translate-x-1'
]">
  <svg class="h-5 w-5 shrink-0"><!-- icon --></svg>
  <span class="text-sm">Nav Item</span>
</a>
```

---

### Filter Sidebar

```html
<aside class="w-full lg:w-72 shrink-0">
  <div class="sticky top-4 space-y-4">
    <div class="p-5 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl space-y-6">
      <!-- Search -->
      <div>
        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-2">Search</p>
        <input class="w-full px-3 py-2 text-sm border border-gray-200 dark:border-gray-700 rounded-lg ..." />
      </div>
      <!-- Filter group -->
      <div>
        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-2">Filter By</p>
        <!-- options -->
      </div>
    </div>
  </div>
</aside>
```

---

### Modals

**Structure:**
```html
<!-- Overlay -->
<div class="fixed inset-0 z-50 bg-gray-500/20 dark:bg-gray-900/60 backdrop-blur-sm transition-opacity" />

<!-- Panel -->
<div class="relative bg-white dark:bg-gray-900 rounded-xl shadow-xl p-6 w-full max-w-2xl mx-auto">
  <!-- Header -->
  <div class="mb-4">
    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Modal Title</h3>
    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Description</p>
  </div>

  <!-- Content -->
  <div class="space-y-4">
    <!-- form fields -->
  </div>

  <!-- Footer -->
  <div class="mt-6 flex justify-end gap-x-3">
    <button class="... secondary button ...">Cancel</button>
    <button class="... primary button ...">Confirm</button>
  </div>
</div>
```

**Transitions (modal entrance):**
```html
<Transition
  enter-active-class="ease-out duration-300"
  enter-from-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
  enter-to-class="opacity-100 translate-y-0 sm:scale-100"
  leave-active-class="ease-in duration-200"
  leave-from-class="opacity-100 translate-y-0 sm:scale-100"
  leave-to-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
/>
```

---

### Flash / Success Message

```html
<div class="fixed inset-0 z-50 flex items-center justify-center">
  <div class="bg-white dark:bg-gray-900 rounded-xl shadow-xl p-6 w-full max-w-sm mx-4 text-center">
    <div class="flex items-center justify-center w-12 h-12 mx-auto mb-4 rounded-full bg-emerald-100 dark:bg-emerald-900/30">
      <svg class="w-6 h-6 text-emerald-600 dark:text-emerald-400"><!-- checkmark --></svg>
    </div>
    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Success</h3>
    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ message }}</p>
    <button class="mt-4 inline-flex items-center justify-center px-6 py-2 text-sm font-medium border border-gray-200 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors duration-200">
      OK
    </button>
  </div>
</div>
```

---

### Pagination

```html
<div class="flex flex-wrap items-center gap-1 mt-8">
  <!-- Disabled prev -->
  <span class="px-4 py-2.5 text-sm leading-4 text-gray-400 dark:text-gray-600 border border-gray-200 dark:border-gray-700 rounded-md cursor-not-allowed">
    &laquo; Prev
  </span>

  <!-- Page link -->
  <a class="px-4 py-2.5 text-sm leading-4 border rounded-md focus:border-emerald-500 focus:text-emerald-500 transition-colors duration-200"
     :class="link.active
       ? 'bg-emerald-600 text-white border-emerald-600'
       : 'bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800'">
    {{ label }}
  </a>
</div>
```

---

## Icons

Always use **inline SVG** from Heroicons. Never import an icon component or use an icon font.

```html
<!-- Standard icon (24px outline, stroke-width 1.5) -->
<svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
  <path stroke-linecap="round" stroke-linejoin="round" d="..." />
</svg>

<!-- Smaller icon (toolbar, table actions) -->
<svg class="h-4 w-4 text-gray-400" ...>

<!-- Hero icon (auth pages, empty states) -->
<svg class="h-8 w-8 text-white" stroke-width="1.8" ...>
```

**Icon sizes:**
| Size | Class | Usage |
|---|---|---|
| Micro | `h-3.5 w-3.5` | Inside badges, tiny inline |
| Small | `h-4 w-4` | Toolbar buttons, table actions, form icons |
| Standard | `h-5 w-5` | Sidebar nav, list items |
| Large | `h-6 w-6` | Modal headers, section icons |
| Hero | `h-8 w-8` | Auth page icon containers |

**Icon colors:**
- Default: `text-gray-400 dark:text-gray-500`
- Active / hover: `text-emerald-600 dark:text-emerald-400`
- On dark background: `text-white`
- On group hover: `group-hover:text-emerald-600`

---

## RTL Support

Every directional Tailwind class must have its RTL counterpart. Use logical properties where available.

```html
<!-- Margin -->
<div class="ltr:mr-3 rtl:ml-3">icon</div>
<div class="ltr:pl-10 rtl:pr-10">input with leading icon</div>

<!-- Text alignment -->
<label class="block text-sm rtl:text-right">Label</label>

<!-- Flex reversal -->
<div class="flex items-center rtl:flex-row-reverse">...</div>

<!-- Translate on hover (nav links) -->
<a class="hover:translate-x-1 rtl:hover:-translate-x-1">Nav item</a>

<!-- Rotate for chevrons pointing right -->
<svg class="rtl:rotate-180"><!-- right chevron --></svg>

<!-- Logical properties (preferred over ltr:/rtl: where supported) -->
<div class="ps-4 pe-4">   <!-- padding-inline-start / end -->
<div class="ms-3 me-3">   <!-- margin-inline-start / end -->
<div class="border-e">    <!-- border-inline-end -->
<div class="border-s">    <!-- border-inline-start -->
<div class="text-start">  <!-- text-align: start -->
```

Direction is applied to the root element of auth pages:
```html
<div :dir="locale === 'ar' ? 'rtl' : 'ltr'">
```

Inside the tenant app layout, RTL is driven by the `dir` attribute on the `<html>` element set by the `HandleLocale` middleware — do not override it on individual components.

---

## Animations & Transitions

| Element | Classes |
|---|---|
| Buttons | `transition-colors duration-200` |
| Nav links | `transition-all duration-300` |
| Dropdown open | `transition duration-200 ease-out` / `scale-95 opacity-0` → `scale-100 opacity-100` |
| Dropdown close | `transition duration-75 ease-in` / `scale-100 opacity-100` → `scale-95 opacity-0` |
| Modal entrance | `ease-out duration-300` / `opacity-0 scale-95` → `opacity-100 scale-100` |
| Modal exit | `ease-in duration-200` / `opacity-100 scale-100` → `opacity-0 scale-95` |
| Table row hover | `transition-all duration-200` |
| Icon hover scale | `group-hover:scale-110 transition-transform` |
| Opacity fade | `transition-opacity duration-200` |
| Spinner | `animate-spin` |

---

## Page Layout Structure

Every tenant page follows this structure:

```
AppLayout
└── Main content area
    ├── Page header (title + count badge + action buttons)
    ├── Optional: flex row with filter sidebar + content
    │   ├── Filter sidebar (w-full lg:w-72, sticky)
    │   └── Main content (flex-1 min-w-0)
    │       ├── Table or card grid
    │       └── Pagination
    └── OR: Single full-width content area
```

Auth pages use `AuthenticationCard` which centers content with a white card on a green-tinted background (`bg-[#F4F9F6]`).

---

## Rules

1. **Never hardcode a color without its dark: variant.**
2. **Never use `text-left` or `text-right` — use `text-start` or `rtl:text-right`.**
3. **Never use `ml-` or `mr-` on directional icons without `ltr:` / `rtl:` pair or logical equivalent.**
4. **Never use a margin between sibling elements — use `gap-` on the parent.**
5. **Never write `rounded` alone for interactive elements — always `rounded-lg` or `rounded-xl`.**
6. **Always include focus states on interactive elements (`focus:outline-none focus:ring-2 ...`).**
7. **Always include `disabled:opacity-50 disabled:cursor-not-allowed` on buttons.**
8. **Icons are always inline SVG from Heroicons — no icon fonts, no component wrappers.**
9. **Auth pages use slate palette; app pages use gray palette.**
10. **Table headers always use `text-start`, never `text-left`.**
