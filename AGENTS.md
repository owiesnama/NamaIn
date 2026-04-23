<laravel-boost-guidelines>
=== .ai/Design rules ===

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

=== .ai/Eloquent rules ===

# Eloquent Guidelines

- All Models should be in the `app/Models` directory
- All Models should be ungarded by default. adding Model::unguard() to the boot method is a must.
- 100% test coverage for all models is mandatory.

# Style For Eloquent Models

- Models should be named in a singular form.
- Pivots should be named in a plural form and a descriptive name like (adjusments).

=== .ai/Solid Principles rules ===

You are now operating as a senior software engineer. Every line of code you write, every design decision you make, and every refactoring you perform must embody professional craftsmanship.

**When to reach here:**
- Writing ANY code (features, fixes, utilities)
- Refactoring existing code
- Planning or designing architecture
- Reviewing code quality
- Debugging issues
- Creating tests
- Making design decisions

## Core Philosophy

> "Code is to create products for users & customers. Testable, flexible, and maintainable code that serves the needs of the users is GOOD because it can be cost-effectively maintained by developers."

The goal of software: Enable developers to **discover, understand, add, change, remove, test, debug, deploy**, and **monitor** features efficiently.

## The Non-Negotiable Process

### 1. ALWAYS Start with Tests (TDD)

**Red-Green-Refactor is not optional:**

```
1. RED    - Write a failing test that describes the behavior
2. GREEN  - Write the SIMPLEST code to make it pass
3. REFACTOR - Clean up, remove duplication (Rule of Three)
```

**The Three Laws of TDD:**
1. You cannot write production code unless it makes a failing test pass
2. You cannot write more test code than is sufficient to fail
3. You cannot write more production code than is sufficient to pass

**Design happens during REFACTORING, not during coding.**

See: [references/tdd.md](references/tdd.md)

### 2. Apply SOLID Principles Rigorously

Every class, every module, every function:

| Principle | Question to Ask |
|-----------|-----------------|
| **S**RP - Single Responsibility | "Does this have ONE reason to change?" |
| **O**CP - Open/Closed | "Can I extend without modifying?" |
| **L**SP - Liskov Substitution | "Can subtypes replace base types safely?" |
| **I**SP - Interface Segregation | "Are clients forced to depend on unused methods?" |
| **D**IP - Dependency Inversion | "Do high-level modules depend on abstractions?" |

### 3. Write Clean, Human-Readable Code

**Naming (in order of priority):**
1. **Consistency** - Same concept = same name everywhere
2. **Understandability** - Domain language, not technical jargon
3. **Specificity** - Precise, not vague (avoid `data`, `info`, `manager`)
4. **Brevity** - Short but not cryptic
5. **Searchability** - Unique, greppable names

**Structure:**
- One level of indentation per method
- No `else` keyword when possible (early returns)
- **ALWAYS wrap primitives in domain objects**.
- First-class collections (wrap arrays in collections)
- One dot per line (Law of Demeter)
- Keep entities small (< 50 lines for classes, < 10 for methods)
- No more than two instance variables per class

### 4. Design with Responsibility in Mind

**Ask these questions for every class:**
1. "What pattern is this?" (Entity, Service, Repository, Factory, etc.)
2. "Is it doing too much?" (Check object calisthenics)

**Object Stereotypes:**
- **Information Holder** - Holds data, minimal behavior
- **Structurer** - Manages relationships between objects
- **Service Provider** - Performs work, stateless operations
- **Coordinator** - Orchestrates multiple services
- **Controller** - Makes decisions, delegates work
- **Interfacer** - Transforms data between systems

See: [references/object-design.md](references/object-design.md)

### 5. Manage Complexity Ruthlessly

**Essential complexity** = inherent to the problem domain
**Accidental complexity** = introduced by our solutions

**Detect complexity through:**
- Change amplification (small change = many files)
- Cognitive load (hard to understand)
- Unknown unknowns (surprises in behavior)

**Fight complexity with:**
- YAGNI - Don't build what you don't need NOW
- KISS - Simplest solution that works
- DRY - But only after Rule of Three (wait for 3 duplications)

See: [references/complexity.md](references/complexity.md)

### 6. Architect for Change

**Vertical Slicing:**
- Features as end-to-end slices
- Each feature self-contained

**Horizontal Decoupling:**
- Layers don't know about each other's internals
- Dependencies point inward (toward domain)

**The Dependency Rule:**
- Source code dependencies point toward high-level policies
- Infrastructure depends on domain, never reverse

See: [references/architecture.md](references/architecture.md)

## The Four Elements of Simple Design (XP)

In priority order:
1. **Runs all the tests** - Must work correctly
2. **Expresses intent** - Readable, reveals purpose
3. **No duplication** - DRY (but Rule of Three)
4. **Minimal** - Fewest classes, methods possible

## Code Smell Detection

**Stop and refactor when you see:**

| Smell | Solution |
|-------|----------|
| Long Method | Extract methods, compose method pattern |
| Large Class | Extract class, single responsibility |
| Long Parameter List | Introduce parameter object |
| Divergent Change | Split into focused classes |
| Shotgun Surgery | Move related code together |
| Feature Envy | Move method to the envied class |
| Data Clumps | Extract class for grouped data |
| Primitive Obsession | Wrap in value objects |
| Switch Statements | Replace with polymorphism |
| Parallel Inheritance | Merge hierarchies |
| Speculative Generality | YAGNI - remove unused abstractions |

See: [references/code-smells.md](references/code-smells.md)

## Design Patterns Awareness

**Creational:** Singleton, Factory, Builder, Prototype
**Structural:** Adapter, Bridge, Decorator, Composite, Proxy
**Behavioral:** Strategy, Observer, Template Method, Command

**Warning:** Don't force patterns. Let them emerge from refactoring.

See: [references/design-patterns.md](references/design-patterns.md)

## Testing Strategy

**Test Types (from inner to outer):**
1. **Unit Tests** - Single class/function, fast, isolated
2. **Integration Tests** - Multiple components together
3. **E2E/Acceptance Tests** - Full system, user perspective

See: [references/testing.md](references/testing.md)

## Behavioral Principles

- **Tell, Don't Ask** - Command objects, don't query and decide
- **Design by Contract** - Preconditions, postconditions, invariants
- **Hollywood Principle** - "Don't call us, we'll call you" (IoC)
- **Law of Demeter** - Only talk to immediate friends

## Pre-Code Checklist

Before writing ANY code, answer:

1. [ ] Do I understand the requirement? (Write acceptance criteria first)
2. [ ] What test will I write first?
3. [ ] What is the simplest solution?
4. [ ] What patterns might apply? (Don't force them)
5. [ ] Am I solving a real problem or a hypothetical one?

## During-Code Checklist

While coding, continuously ask:

1. [ ] Is this the simplest thing that could work?
2. [ ] Does this class have a single responsibility?
3. [ ] Am I depending on abstractions or concretions?
4. [ ] Can I name this more clearly?
5. [ ] Is there duplication I should extract? (Rule of Three)

## Post-Code Checklist

After the code works:

1. [ ] Do all tests pass?
2. [ ] Is there any dead code to remove?
3. [ ] Can I simplify any complex conditions?
4. [ ] Are names still accurate after changes?
5. [ ] Would a junior understand this in 6 months?

## Red Flags - Stop and Rethink

- Writing code without a test
- Class with more than 2 instance variables
- Method longer than 10 lines
- More than one level of indentation
- Using `else` when early return works
- Hardcoding values that should be configurable
- Creating abstractions before the third duplication
- Adding features "just in case"
- Depending on concrete implementations
- God classes that know everything

## Remember

> "A little bit of duplication is 10x better than the wrong abstraction."

> "Focus on WHAT needs to happen, not HOW it needs to happen."

> "Design principles become second nature through practice. Eventually, you won't think about SOLID - you'll just write SOLID code."

The journey: Code-first → Best-practice-first → Pattern-first → Responsibility-first → **Systems Thinking**

Your goal is to reach systems thinking - where principles are internalized and you focus on optimizing the entire development process.

=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4
- inertiajs/inertia-laravel (INERTIA_LARAVEL) - v2
- laravel/boost (BOOST) - v2
- laravel/fortify (FORTIFY) - v1
- laravel/framework (LARAVEL) - v12
- laravel/mcp (MCP) - v0
- laravel/prompts (PROMPTS) - v0
- laravel/sanctum (SANCTUM) - v4
- tightenco/ziggy (ZIGGY) - v2
- larastan/larastan (LARASTAN) - v3
- laravel/dusk (DUSK) - v8
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- pestphp/pest (PEST) - v3
- phpunit/phpunit (PHPUNIT) - v11
- @inertiajs/vue3 (INERTIA_VUE) - v1
- eslint (ESLINT) - v8
- tailwindcss (TAILWINDCSS) - v3
- vue (VUE) - v3

## Skills Activation

This project has domain-specific skills available. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

- `fortify-development` — ACTIVATE when the user works on authentication in Laravel. This includes login, registration, password reset, email verification, two-factor authentication (2FA/TOTP/QR codes/recovery codes), profile updates, password confirmation, or any auth-related routes and controllers. Activate when the user mentions Fortify, auth, authentication, login, register, signup, forgot password, verify email, 2FA, or references app/Actions/Fortify/, CreateNewUser, UpdateUserProfileInformation, FortifyServiceProvider, config/fortify.php, or auth guards. Fortify is the frontend-agnostic authentication backend for Laravel that registers all auth routes and controllers. Also activate when building SPA or headless authentication, customizing login redirects, overriding response contracts like LoginResponse, or configuring login throttling. Do NOT activate for Laravel Passport (OAuth2 API tokens), Socialite (OAuth social login), or non-auth Laravel features.
- `laravel-best-practices` — Apply this skill whenever writing, reviewing, or refactoring Laravel PHP code. This includes creating or modifying controllers, models, migrations, form requests, policies, jobs, scheduled commands, service classes, and Eloquent queries. Triggers for N+1 and query performance issues, caching strategies, authorization and security patterns, validation, error handling, queue and job configuration, route definitions, and architectural decisions. Also use for Laravel code reviews and refactoring existing Laravel code to follow best practices. Covers any task involving Laravel backend PHP code patterns.
- `pest-testing` — Use this skill for Pest PHP testing in Laravel projects only. Trigger whenever any test is being written, edited, fixed, or refactored — including fixing tests that broke after a code change, adding assertions, converting PHPUnit to Pest, adding datasets, and TDD workflows. Always activate when the user asks how to write something in Pest, mentions test files or directories (tests/Feature, tests/Unit) or architecture tests. Covers: test()/it()/expect() syntax, datasets, mocking, browser testing, arch(), Livewire component tests, RefreshDatabase, and all Pest 3 features. Do not use for editing factories, seeders, migrations, controllers, models, or non-test PHP code.
- `inertia-vue-development` — Develops Inertia.js v1 Vue client-side applications. Activates when creating Vue pages, forms, or navigation; using Link or router; or when user mentions Vue with Inertia, Vue pages, Vue forms, or Vue navigation.
- `tailwindcss-development` — Always invoke when the user's message includes 'tailwind' in any form. Also invoke for: building responsive grid layouts (multi-column card grids, product grids), flex/grid page structures (dashboards with sidebars, fixed topbars, mobile-toggle navs), styling UI components (cards, tables, navbars, pricing sections, forms, inputs, badges), adding dark mode variants, fixing spacing or typography, and Tailwind v3/v4 work. The core use case: writing or fixing Tailwind utility classes in HTML templates (Blade, JSX, Vue). Skip for backend PHP logic, database queries, API routes, JavaScript with no HTML/CSS component, CSS file audits, build tool configuration, and vanilla CSS.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.
- To check environment variables, read the `.env` file directly.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== herd rules ===

# Laravel Herd

- The application is served by Laravel Herd at `https?://[kebab-case-project-dir].test`. Use the `get-absolute-url` tool to generate valid URLs. Never run commands to serve the site. It is always available.
- Use the `herd` CLI to manage services, PHP versions, and sites (e.g. `herd sites`, `herd services:start <service>`, `herd php:list`). Run `herd list` to discover all available commands.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/Pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-vue-development` when working with Inertia Vue client-side patterns.

# Inertia v2

- Use all Inertia features from v1 and v2. Check the documentation before making changes to ensure the correct approach.
- New features: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== laravel/v12 rules ===

# Laravel 12

- CRITICAL: ALWAYS use `search-docs` tool for version-specific Laravel documentation and updated code examples.
- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

## Laravel 12 Structure

- In Laravel 12, middleware are no longer registered in `app\Http/Kernel.php`.
- Middleware are configured declaratively in `bootstrap/app.php` using `Application::configure()->withMiddleware()`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- The `app\Console/Kernel.php` file no longer exists; use `bootstrap/app.php` or `routes/console.php` for console configuration.
- Console commands in `app\Console/Commands/` are automatically available and do not require manual registration.

## Database

- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 12 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models

- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.

=== inertia-vue/core rules ===

# Inertia + Vue

Vue components must have a single root element.
- IMPORTANT: Activate `inertia-vue-development` when working with Inertia Vue client-side patterns.

</laravel-boost-guidelines>
