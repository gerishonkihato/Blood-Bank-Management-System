# KNBTS Design System Reference Guide

## 🎨 Color Palette

### Primary Colors
```
--primary: #c41e3a          (Deep Red - Brand)
--primary-dark: #8b0000     (Dark Red - Hover States)
--primary-light: #e74c3c    (Light Red - Secondary)
```

### Role-Based Accent Colors
```
--accent-green: #27ae60     (Donors - Life-saving)
--accent-purple: #9b59b6    (Recipients - Healthcare)
--accent-blue: #3498db      (Admin - Authority)
--accent-orange: #f39c12    (Requests - Attention)
```

### Neutral Colors
```
--text-dark: #2c3e50        (Primary Text)
--text-light: #7f8c8d       (Secondary Text)
--bg-light: #f8f9fa         (Light Background)
--bg-lighter: #f3f5f7       (Lighter Background)
--border-color: #e0e6ed     (Borders)
```

### Shadow System
```
--shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.08)
--shadow-md: 0 4px 16px rgba(0, 0, 0, 0.12)
--shadow-lg: 0 8px 24px rgba(0, 0, 0, 0.15)
```

---

## 🔤 Typography

### Fonts
- **Headings**: Poppins (Bold, Weights: 600-700)
- **Body Text**: Inter (Regular, Weights: 400-500)
- **Import**: Google Fonts API

### Font Sizes
```
h1: 2.5rem (40px)
h2: 2rem   (32px)
h3: 1.5rem (24px)
h4: 1.25rem (20px)
p:  1rem   (16px) - default
```

### Font Weights
```
Light:   300
Regular: 400
Medium:  500
Bold:    600
Extra Bold: 700
Black:   800
```

---

## 🔘 Button Styles

### Button Variants

#### Primary Button (.btn-primary)
```css
Background: Linear gradient (red)
Color: White
Padding: 1rem
Border-radius: 8px
Font-weight: 600
Text-transform: uppercase
```

#### Success Button (.btn-success)
```css
Background: Linear gradient (green)
Color: White
Hover: Elevated with shadow
```

#### Approve Button (.btn-approve)
```css
Background: Green gradient
Padding: 0.6rem 1.2rem
Font-size: 0.8rem
```

#### Reject Button (.btn-reject)
```css
Background: Red gradient
Padding: 0.6rem 1.2rem
Font-size: 0.8rem
```

#### View Button (.btn-view)
```css
Background: Blue gradient
Padding: 0.6rem 1.2rem
Font-size: 0.8rem
```

### Button Hover Effects
- All buttons: `transform: translateY(-2px)`
- Shadow elevation on hover
- Smooth 0.3s transitions

---

## 📦 Component Sizes

### Padding
```
xs: 0.5rem
sm: 1rem
md: 1.5rem
lg: 2rem
xl: 2.5rem
3xl: 3.5rem
```

### Border Radius
```
Small:  4px
Medium: 8px
Large:  12px
Extra:  16px
```

### Spacing (Gap)
```
1rem:   16px (default)
1.5rem: 24px
2rem:   32px
2.5rem: 40px
3rem:   48px
```

---

## 🎯 Component Guide

### Header Component
```
Background: Red gradient
Color: White
Padding: 1.5rem 0
Box-shadow: shadow-md
Position: Sticky
Z-index: 100
Max-width: 1400px
```

### Card Component
```
Background: White
Border: 1px solid #e0e6ed
Border-radius: 12px
Padding: 2rem
Box-shadow: shadow-sm
Transition: shadow on hover
```

### Stat Card
```
Extends: Card
Border-left: 4px (colored)
Padding: 2rem
Hover: Elevated (shadow-md) + translateY(-4px)
```

### Form Inputs
```
Width: 100%
Padding: 0.95rem 1rem
Border: 2px solid var(--border-color)
Border-radius: 8px
Background: var(--bg-light)
Font-family: inherit
Transition: 0.3s ease
Focus: Border-color primary + shadow
```

### Tables
```
Border-collapse: collapse
Font-size: 0.9rem
th: Uppercase, weight 600, background light
td: Left-aligned, padded 1rem
Hover row: Light background
Last row: No bottom border
```

### Status Badges
```
Display: inline-block
Padding: 0.5rem 1rem
Border-radius: 20px
Font-size: 0.8rem
Font-weight: 600
Text-transform: uppercase

Variants:
- Pending: Yellow (#fff3cd)
- Approved: Green (#d4edda)
- Rejected: Red (#f8d7da)
- Completed: Blue (#cfe2ff)
```

---

## 📐 Responsive Grid System

### Breakpoints
```
Desktop:     1024px and up
Tablet:      768px - 1024px
Mobile:      480px - 768px
Small Mobile: below 480px
```

### Grid Templates
```
Stats Grid:
Desktop: repeat(auto-fit, minmax(260px, 1fr))
Tablet:  1fr (full-width)
Mobile:  1fr (full-width)

Content Grid:
Desktop: 2fr 1fr (sidebar layout)
Tablet:  1fr (single column)

Features Grid:
Desktop: repeat(auto-fit, minmax(280px, 1fr))
Mobile:  1fr (single column)

Inventory Grid:
Desktop: repeat(auto-fit, minmax(150px, 1fr))
Mobile:  repeat(2, 1fr)
```

---

## ✨ Animation & Transitions

### Transition Timing
```
--transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1)
```

### Hover Effects
- Buttons: `translateY(-2px)`
- Cards: `translateY(-4px) or -8px`
- Inventory: `translateY(-3px)`

### Focus Effects
- Inputs: `border-color primary + shadow`
- Buttons: `box-shadow with primary light`

---

## 🔄 CSS Variables Usage

### How to Use Variables
```css
/* Property with variable */
color: var(--text-dark);
background: var(--bg-light);
box-shadow: var(--shadow-md);

/* With fallback */
color: var(--text-dark, #2c3e50);
```

### Common Variable Patterns
```css
/* Colors */
var(--primary)
var(--accent-green)
var(--text-dark)
var(--bg-light)

/* Shadows */
var(--shadow-sm)
var(--shadow-md)

/* Transitions */
var(--transition)
```

---

## 🎭 Gradient Patterns

### Header Gradient
```css
linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%)
```

### Button Gradients
```css
/* Red Button */
linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%)

/* Green Button */
linear-gradient(135deg, var(--accent-green) 0%, #229954 100%)

/* Purple Button */
linear-gradient(135deg, var(--accent-purple) 0%, #8e44ad 100%)
```

### Hero Background
```css
linear-gradient(rgba(0, 0, 0, 0.35), rgba(196, 30, 58, 0.4))
```

---

## 📋 Component Usage Examples

### Using the Card Component
```html
<div class="card">
    <h2>Section Title</h2>
    <p>Card content goes here</p>
</div>
```

### Using Stat Cards
```html
<div class="stat-card donors">
    <h3>Total Donors</h3>
    <div class="value">150</div>
</div>
```

### Using Form Elements
```html
<div class="form-group">
    <label for="username">Username</label>
    <input type="text" id="username" name="username" 
           placeholder="Enter username">
</div>
```

### Using Buttons
```html
<!-- Primary -->
<button class="btn btn-primary">Save</button>

<!-- Success -->
<a href="#" class="btn btn-success">Proceed</a>

<!-- Approve/Reject -->
<button class="btn btn-approve">Approve</button>
<button class="btn btn-reject">Reject</button>
```

### Using Status Badges
```html
<span class="status-badge status-pending">Pending</span>
<span class="status-badge status-approved">Approved</span>
<span class="status-badge status-rejected">Rejected</span>
```

---

## 🔧 Customization Guide

### To Change Brand Color
1. Update `--primary: #c41e3a` in root variables
2. All red-dependent elements will auto-update

### To Adjust Spacing
1. Modify base size in CSS variables
2. Use multiples (1.5x, 2x, etc.)

### To Change Fonts
1. Update Google Fonts import URL
2. Change font-family in body/h1-h6

### To Add New Status Badge
```css
.status-custom {
    background: #your-color;
    color: #text-color;
    border: 1px solid #border-color;
}
```

---

## 📱 Mobile-First Tips

- Always check mobile view (max-width: 480px)
- Use responsive font sizes
- Ensure touch targets are 44px minimum
- Test all breakpoints
- Check form inputs on mobile
- Verify button sizes on small screens

---

## ✅ Quality Checklist

- [ ] All pages load correctly
- [ ] Color contrast meets WCAG AA
- [ ] Forms are mobile-friendly
- [ ] All buttons have hover states
- [ ] Shadows render properly
- [ ] Fonts load from Google Fonts
- [ ] No inline styles (only in style.css)
- [ ] Responsive layout works at all breakpoints
- [ ] Focus states visible on keyboard nav
- [ ] Icons/emojis render correctly

---

## 📞 Support

For design system questions or updates:
1. Check this guide first
2. Review style.css for implementation
3. Test on multiple browsers and devices
4. Maintain consistency across new pages

---

**Version**: 1.0 - March 31, 2026  
**Theme**: Modern Red-Accented  
**Status**: Production Ready ✅
