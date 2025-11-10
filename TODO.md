# TODO: Update Navbar Based on Routes

- [x] Update app.component.ts to add OnInit, currentUrl property, and ngOnInit with router subscription for tracking current URL.
- [x] Modify the template in app.component.ts to conditionally show "Inscription" link only if currentUrl !== '/admin/login'.
- [x] Modify the template in app.component.ts to conditionally show "Admin" link only if currentUrl !== '/register' and !isAuthenticated.
- [ ] Test navigation to /admin/login and /register to verify links are hidden as specified.
