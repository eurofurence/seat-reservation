# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

# EF Seating Reservation Admin Panel

## Architecture

This application uses **Laravel 12 + Inertia.js + Vue 3** with **shadcn/vue** components. The core domain models represent a hierarchical seating structure:

- **Room** → contains multiple **Blocks** (seating sections)
- **Block** → contains multiple **Rows** (with positioning/rotation)
- **Row** → contains multiple **Seats** 
- **Seat** → can be booked for **Events**

## Development Commands

### Frontend Development
```bash
npm run dev            # Development with HMR
npm run build          # Production build
```

### Backend Development
```bash
php artisan serve      # Start Laravel development server
php artisan test       # Run PHPUnit tests
php artisan test --filter="TestClass" # Run specific test class
php artisan test --filter="test_method_name" # Run specific test method
vendor/bin/pint        # Format PHP code with Laravel Pint

# Route management
php artisan route:list # View all routes with names, methods, and URIs
php artisan route:list --name=admin # Filter routes by name pattern
php artisan route:list --path=admin # Filter routes by URI pattern
php artisan route:list --method=GET # Filter routes by HTTP method
php artisan route:clear # Clear route cache
```

### Database
```bash
php artisan migrate    # Run migrations
php artisan migrate:fresh --seed  # Fresh database with seeding
```

## Request Handling Patterns

### ✅ Correct Inertia.js Patterns

#### Navigation with Link Component
Always use Inertia's Link component for navigation:

```vue
<script setup>
import { Link } from '@inertiajs/vue3'
</script>

<template>
  <!-- Correct: Use Link with route helper -->
  <Link :href="route('admin.rooms.layout', room.id)">
    <Button as="span">Floor Plan</Button>
  </Link>
  
  <!-- For programmatic navigation, use router -->
  <Button @click="router.visit(route('admin.rooms.edit', room.id))">Edit</Button>
</template>
```

#### Form Submissions
Always use the Inertia.js `useForm` helper for form operations:

```javascript
import { useForm } from '@inertiajs/vue3'

const form = useForm({
  name: '',
  email: ''
})

// Submit
form.post(route('admin.users.store'), {
  onSuccess: () => form.reset()
})

// Update
form.put(route('admin.users.update', user.id))

// Delete
const deleteForm = useForm({})
deleteForm.delete(route('admin.users.destroy', user.id))
```

### ❌ Avoid These Patterns

- Don't use `/api` routes with Inertia.js - use named routes
- Don't use `router.post()` directly for forms - use `form.post()`
- Don't use `fetch()` for form submissions - use Inertia forms
- Don't use `window.open()` or `window.location` for navigation - use Link component
- Don't use `<a>` tags directly - use Inertia's Link component

## Laravel Controller Patterns

```php
// Form submissions - redirect with flash message
public function store(Request $request)
{
    $user = User::create($request->validated());
    return redirect()->route('admin.users.index')
        ->with('success', 'User created successfully!');
}

// Page loads - return Inertia render
public function index()
{
    return Inertia::render('Admin/Users/Index', [
        'users' => User::all(),
        'title' => 'Users',
        'breadcrumbs' => []
    ]);
}
```

## Toast Notifications

The admin layout uses shadcn/vue toast notifications that automatically display Laravel flash messages:

### Laravel Controller Usage
```php
// Success message
return redirect()->route('admin.rooms.index')
    ->with('success', 'Room created successfully!');

// Error message
return back()->with('error', 'Something went wrong.');

// Warning message
return back()->with('warning', 'Please review your input.');

// Info message
return back()->with('info', 'Processing may take a few moments.');
```

### Manual Toast Usage in Vue (if needed)
```javascript
import { useToast } from '@/Components/ui/toast'

const { success, error, warning, info } = useToast()

// Show different toast types
success('Success', 'Operation completed!')
error('Error', 'An error occurred')
warning('Warning', 'Please be careful')
info('Info', 'Just letting you know')

// With options
toast.success('Saved!', {
  duration: 4000,
  description: 'Your changes have been saved.'
})
```

Flash messages are automatically displayed as toasts on:
- Page load
- After Inertia navigation
- After form submissions

## Route Structure

### Admin Routes
All admin routes use `admin.` prefix with middleware for authentication and shared admin data:

```php
Route::prefix('admin')->name('admin.')->middleware(['auth', ShareAdminData::class])->group(function () {
    Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');
    Route::resource('events', EventAdminController::class);
    Route::resource('rooms', RoomAdminController::class);
    Route::get('/rooms/{room}/layout', [RoomLayoutController::class, 'edit'])->name('rooms.layout');
});
```

### Actual Route Names (from web.php)
- `admin.dashboard` - Admin dashboard
- `admin.events.index/store/show/destroy` - Event management
- `admin.rooms.index/store/edit/update/destroy` - Room management  
- `admin.rooms.layout` - Room floor plan editor
- `admin.rooms.blocks.create/delete` - Block management within rooms

## UI Components & TypeScript

### Layout System
Uses `defineOptions({ layout: AdminLayout })` for automatic layout wrapping:

```vue
<script setup lang="ts">
import AdminLayout from '@/Admin/Layouts/AdminLayout.vue'

defineOptions({ layout: AdminLayout })

const props = defineProps<{
  title: string
  breadcrumbs?: Array<any>
}>()
</script>

<template>
  <Head :title="title" />
  <!-- Page content only - no layout wrapper needed -->
</template>
```

### TypeScript Configuration
- Uses strict TypeScript with path mapping: `@/*` → `./resources/js/*`
- Types defined in `resources/js/types`
- All Vue components should use `<script setup lang="ts">`

### shadcn/vue Components
Import from component folders using destructuring:
```vue
<script setup lang="ts">
// Import multiple components from the same folder
import { Card, CardHeader, CardTitle, CardContent } from '@/Components/ui/card'
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/Components/ui/dialog'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Table } from '@/Components/ui/table'
</script>
```

Each component now lives in its own folder with an `index.ts` export file. Always use destructured imports from the folder path, not individual `.vue` files.

## Domain Model Relationships

### Seating Hierarchy
```
Room (stage_x, stage_y positioning)
├── Block[] (positioned sections with rotation, sorted)
    ├── Row[] (named rows, sorted)
        ├── Seat[] (numbered seats with labels, sorted)
```

### Key Model Methods
- `Seat::isBookedForEvent($eventId)` - Check seat availability
- `Seat::getFullLabel()` - Returns "Block-Row-Label" format
- Models use `$guarded = []` and explicit `$fillable` arrays
- Most models disable timestamps with `$timestamps = false`

## File Structure

### Vue Components
- `resources/js/Pages/Admin/` - Admin page components
- `resources/js/Admin/Layouts/AdminLayout.vue` - Main admin layout
- `resources/js/Components/ui/` - shadcn/vue components
- `resources/js/lib/utils.ts` - Utility functions

### Controllers
- `app/Http/Controllers/Admin/` - Admin-specific controllers
- `app/Http/Middleware/ShareAdminData.php` - Shares data with admin views

## Common Issues

### Route 404s
- Ensure routes don't have `/api` prefix for Inertia pages
- Use `route()` helper in JavaScript instead of hardcoded URLs
- Check route names match between PHP and JavaScript

### TypeScript Errors
- Run `npm run build` to check for TypeScript compilation errors
- Ensure proper typing for props and component imports

### Layout Issues
- Don't manually wrap templates in layout components
- Use `defineOptions({ layout: AdminLayout })` only
- Flash messages are handled globally in AdminLayout

## Booking Code System

### Architecture
- 2-character alphanumeric booking codes (A-Z, 0-9) for easy ticket pickup
- Generated for **ALL** user interface bookings (regular users and admins)
- **NOT** generated for admin manual bookings (type: 'admin')
- Session-unique codes with collision detection

### Implementation Patterns

#### Code Generation
```php
// BookingController::generateUniqueBookingCode()
private function generateUniqueBookingCode(): string
{
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    while (true) {
        $code = '';
        for ($i = 0; $i < 2; $i++) {
            $code .= $characters[rand(0, $charactersLength - 1)];
        }
        if (!Booking::where('booking_code', $code)->exists()) {
            return $code;
        }
    }
}
```

#### User Interface vs Admin Manual Bookings
```php
// User interface bookings (through BookingController) - GET booking codes
$bookingCode = $this->generateUniqueBookingCode();
foreach ($data['seats'] as $seatData) {
    $event->bookings()->create([
        'booking_code' => $bookingCode,  // Same code for all seats in one booking
        'type' => 'online'
    ]);
}

// Admin manual bookings (through EventAdminController) - NO booking codes  
$bookings[] = [
    'type' => 'admin',    // Mark as admin booking
    'user_id' => null,    // No user association
    'booking_code' => null // No booking code for manual bookings
];
```

#### Admin Dashboard Lookup
```php
// AdminController::lookupBookingCode()
public function lookupBookingCode(Request $request)
{
    $request->validate(['booking_code' => 'required|string|size:2']);
    $bookingCode = strtoupper($request->booking_code);
    
    $booking = Booking::where('booking_code', $bookingCode)
        ->with('event')
        ->first();
        
    if (!$booking) {
        return back()->withErrors(['booking_code' => 'No booking found with this code.']);
    }
    
    // Redirect to event with booking code filter
    return redirect()->route('admin.events.show', $booking->event->id)
        ->with(['bookingcode' => $bookingCode]);
}
```

## Performance Optimization Patterns

### Database Query Optimization
The codebase implements careful query optimization to avoid N+1 problems:

```php
// EventAdminController::show - Only load essential fields
$bookingsQuery = Booking::where('event_id', $id)
    ->select('id', 'event_id', 'user_id', 'seat_id', 'name', 'comment', 'picked_up_at', 'created_at', 'booking_code')
    ->with([
        'user:id,name',
        'seat:id,row_id,label',
        'seat.row:id,block_id,name', 
        'seat.row.block:id,name'
    ]);

// Load room data separately to avoid heavy loading
$room = $event->room()->select('id', 'name')->first();
```

### Manual Booking Controller Pattern
Admin manual bookings use different validation and field names:

```php
// EventAdminController::manualBooking validates 'guest_name'
$request->validate([
    'guest_name' => 'required|string|max:255',  // Note: guest_name, not name
    'comment' => 'nullable|string|max:1000',
    'seat_ids' => 'required|array|min:1',
    'seat_ids.*' => 'required|integer|exists:seats,id'
]);

// Vue component sends guest_name field  
const form = useForm({
    guest_name: manualBookingForm.value.guestName,
    comment: manualBookingForm.value.comment,
    seat_ids: selectedSeats.value
})
```

## Testing Patterns

### Inertia.js Test Data Access
```php
// Correct way to access Inertia props in tests
$props = $response->getOriginalContent()->getData()['page']['props'];
$bookings = $props['bookings'] ?? null;

// Handle paginated results
if (is_object($bookings) && method_exists($bookings, 'items')) {
    $bookingItems = $bookings->items();
} elseif (is_object($bookings) && isset($bookings->data)) {
    $bookingItems = $bookings->data;
}
```

### PDF Generation Testing
```php
// SeatingCards tests require picked_up_at to be set
$booking = Booking::factory()->create([
    'event_id' => $event->id,
    'seat_id' => $seat->id,
    'picked_up_at' => now()  // Required for PDF generation
]);
```