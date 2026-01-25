# ASPRI Frontend - Component Structure

## Overview

Frontend mengikuti Angular best practices dengan memisahkan template HTML, CSS, dan TypeScript logic untuk setiap component.

## Component Structure

Setiap component memiliki 3 file terpisah:

```
component-name/
├── component-name.component.ts    # TypeScript logic
├── component-name.component.html  # HTML template
└── component-name.component.css   # Component styles
```

### Benefits

1. **Separation of Concerns** - Logic, markup, dan styling terpisah
2. **Easier Maintenance** - Mudah menemukan dan mengubah specific aspects
3. **Better Collaboration** - Designer bisa fokus di HTML/CSS, developer di TypeScript
4. **Reusability** - CSS classes bisa di-reuse across components
5. **Testing** - Lebih mudah test component logic tanpa template
6. **IDE Support** - Better IntelliSense dan syntax highlighting

## Components

### 1. Landing Component
**Path**: `src/app/pages/landing/`

**Files**:
- `landing.component.ts` - Component logic, theme/language toggle
- `landing.component.html` - Homepage markup dengan hero, features, footer
- `landing.component.css` - Styles untuk feature cards

**Responsibilities**:
- Display hero section dengan call-to-action
- Show app features dalam card grid
- Theme dan language switching
- Navigation ke login/register

**CSS Classes**:
```css
.feature-card        - Card wrapper untuk setiap fitur
.feature-icon        - Icon container dengan background
.feature-title       - Title text styling
.feature-description - Description text styling
```

### 2. Login Component
**Path**: `src/app/pages/login/`

**Files**:
- `login.component.ts` - Form logic, validation, authentication
- `login.component.html` - Login form markup
- `login.component.css` - Form styling dengan error states

**Responsibilities**:
- Email/password form dengan validation
- Submit ke Supabase authentication
- Error handling dan display
- Theme dan language toggle
- Navigation ke register page

**CSS Classes**:
```css
.logo-container      - App logo styling
.form-container      - Form wrapper dengan shadow
.form-group          - Individual form field wrapper
.form-label          - Label styling
.form-input          - Input field dengan focus states
.input-error         - Error state untuk invalid inputs
.error-message       - Error text display
.alert-error         - Error alert box
.submit-button       - Primary action button
.loading-spinner     - Loading animation
.toolbar             - Top toolbar untuk language/theme
```

### 3. Register Component
**Path**: `src/app/pages/register/`

**Files**:
- `register.component.ts` - Registration form logic, password matching
- `register.component.html` - Registration form dengan confirm password
- `register.component.css` - Form styling dengan success states

**Responsibilities**:
- Email/password/confirm password form
- Password matching validation
- User registration via Supabase
- Success message display
- Theme dan language toggle

**CSS Classes**:
```css
.alert-success       - Success message styling
.success-button      - Button untuk go to login setelah success
(+ semua classes dari login component)
```

### 4. Dashboard Component
**Path**: `src/app/pages/dashboard/`

**Files**:
- `dashboard.component.ts` - Dashboard logic, profile loading
- `dashboard.component.html` - Dashboard layout dengan stats dan profile
- `dashboard.component.css` - Stats cards dan profile card styling

**Responsibilities**:
- Display user statistics (chat, notes, events, finance)
- Show user profile information
- Header dengan logout button
- Theme dan language toggle

**CSS Classes**:
```css
.header              - Top navigation bar
.stats-grid          - Grid layout untuk stat cards
.stat-card           - Individual statistic card
.stat-icon           - Icon wrapper dengan colored background
.stat-value          - Numeric value display
.stat-label          - Label text
.profile-card        - User profile information card
.profile-title       - Profile section title
.profile-info        - Profile items container
.profile-item-label  - Profile field labels
.profile-item-value  - Profile field values
.logout-button       - Logout button styling
```

## Styling Guidelines

### Tailwind Utilities

Components menggunakan Tailwind CSS dengan `@apply` directive di CSS files:

```css
.example-class {
  @apply px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600;
}
```

### Dark Mode Support

Semua components mendukung dark mode dengan classes:

```css
.text-color {
  @apply text-gray-900 dark:text-white;
}

.bg-color {
  @apply bg-white dark:bg-gray-800;
}
```

### Responsive Design

Mobile-first approach dengan responsive utilities:

```html
<div class="grid md:grid-cols-2 lg:grid-cols-4 gap-6">
  <!-- Grid: 1 col mobile, 2 cols tablet, 4 cols desktop -->
</div>
```

### Consistent Spacing

- Container: `container mx-auto px-4`
- Section padding: `py-20` atau `py-8`
- Card padding: `p-6` atau `p-8`
- Gaps: `space-x-4`, `space-y-3`, `gap-6`

### Color Palette

**Primary**: Indigo
- `bg-indigo-600` - Primary buttons, accents
- `text-indigo-600` - Links, icons

**Semantic Colors**:
- Red: Error states, logout button
- Green: Success messages, notes icon
- Purple: Schedule/calendar icon
- Orange: Finance icon

**Neutral**:
- Light mode: `gray-50` to `gray-900`
- Dark mode: `gray-800` to `white`

## Component Communication

### Services Used

1. **AuthService** - User authentication, profile management
2. **SupabaseService** - Direct Supabase client access
3. **ThemeService** - Theme switching (light/dark)
4. **TranslateService** - I18n translations

### Data Flow

```
User Action → Component Method → Service → API/Supabase
                     ↓
              Update Component State
                     ↓
              Template Re-render
```

## Template Best Practices

### 1. Use Semantic HTML
```html
<header>, <main>, <section>, <footer>
```

### 2. Conditional Rendering
```html
<div *ngIf="condition">...</div>
<div *ngFor="let item of items">...</div>
```

### 3. Event Binding
```html
<button (click)="handleClick()">Click</button>
```

### 4. Property Binding
```html
<input [disabled]="isDisabled" />
<div [class.active]="isActive"></div>
```

### 5. Two-way Binding (Forms)
```html
<input [(ngModel)]="value" />
<!-- Or with Reactive Forms -->
<input [formControl]="emailControl" />
```

## CSS Organization

### File Structure
```css
/* 1. Layout Classes */
.container { ... }
.header { ... }

/* 2. Component-specific Classes */
.feature-card { ... }
.stat-card { ... }

/* 3. Utility Classes */
.loading-spinner { ... }
.error-message { ... }
```

### Naming Convention

- **BEM-inspired**: `.block`, `.block__element`, `.block--modifier`
- **Descriptive**: Names explain purpose, not appearance
- **Consistent**: Same naming pattern across components

Examples:
```css
.profile-card           (block)
.profile-card__title    (element)
.profile-card--active   (modifier)
```

## Adding New Components

### 1. Generate Component
```bash
ng generate component pages/new-page
```

### 2. Create Separate Files
- Move template to `new-page.component.html`
- Move styles to `new-page.component.css`
- Update component decorator:
```typescript
@Component({
  selector: 'app-new-page',
  templateUrl: './new-page.component.html',
  styleUrl: './new-page.component.css'
})
```

### 3. Add Routing
Update `app.routes.ts`:
```typescript
{
  path: 'new-page',
  loadComponent: () => import('./pages/new-page/new-page.component')
    .then(m => m.NewPageComponent)
}
```

### 4. Add Translations
Update `id.json` dan `en.json`:
```json
{
  "newPage": {
    "title": "Judul Halaman",
    "description": "Deskripsi"
  }
}
```

### 5. Apply Consistent Styling
- Use existing CSS classes where possible
- Follow Tailwind utilities pattern
- Support dark mode
- Ensure responsive design

## Performance Considerations

1. **Lazy Loading** - All routes use `loadComponent()`
2. **OnPush Change Detection** - Consider adding for complex components
3. **TrackBy Functions** - Use with `*ngFor` for large lists
4. **Async Pipe** - Prefer over manual subscriptions
5. **Pure Pipes** - Create custom pipes for complex transformations

## Maintenance

### When to Refactor

- Template > 100 lines → Split into child components
- CSS > 200 lines → Split into multiple files or use @import
- Component logic > 300 lines → Extract services or helper functions

### Code Review Checklist

- [ ] Template separated from component
- [ ] CSS classes follow naming convention
- [ ] Dark mode styles included
- [ ] Responsive breakpoints tested
- [ ] Translations added for all text
- [ ] Form validation implemented
- [ ] Loading states handled
- [ ] Error states handled
- [ ] Accessibility attributes added (aria-label, role, etc.)

## Resources

- [Angular Style Guide](https://angular.dev/style-guide)
- [Tailwind CSS Docs](https://tailwindcss.com/docs)
- [ngx-translate](https://github.com/ngx-translate/core)
- [Supabase Auth](https://supabase.com/docs/guides/auth)
