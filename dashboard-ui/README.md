# React + Vite

This template provides a minimal setup to get React working in Vite with HMR and some ESLint rules.

Currently, two official plugins are available:

- [@vitejs/plugin-react](https://github.com/vitejs/vite-plugin-react/blob/main/packages/plugin-react/README.md) uses [Babel](https://babeljs.io/) for Fast Refresh
- [@vitejs/plugin-react-swc](https://github.com/vitejs/vite-plugin-react-swc) uses [SWC](https://swc.rs/) for Fast Refresh

### Requirements: 
1. The Mutable survey in the parent project must have an additional variable `last_editing_user`
2. Child form names have to match parent form names exactly - they will otherwise fail to copy over due to the "_complete" variables being based on the survey name.
    - Name mismatches will result in data not being copied from parent to child
3. Child instruments must have every variable present in the parent intake surveys (mutable, and immutable)
4. Each child project must have an additional variable `universal_id` within the immutable intake survey
5. Each child project must have an additional variable `dashboard_submission_user` within their first survey (own)
 
### Default naming: 
- Immutable survey (1) : `Intake`
- Mutable survey (2) : `Mutable Intake`