# React + Vite

This template provides a minimal setup to get React working in Vite with HMR and some ESLint rules.

Currently, two official plugins are available:

- [@vitejs/plugin-react](https://github.com/vitejs/vite-plugin-react/blob/main/packages/plugin-react/README.md) uses [Babel](https://babeljs.io/) for Fast Refresh
- [@vitejs/plugin-react-swc](https://github.com/vitejs/vite-plugin-react-swc) uses [SWC](https://swc.rs/) for Fast Refresh

### Requirements: 

1. Child form names have to match parent form names exactly - they will otherwise fail to copy over due to the "_complete" variables being based on the survey name.
    - Name mismatches will result in data not being copied from parent to child
2. Child instruments must have every variable present in the parent intake surveys (mutable, and immutable)
3. Each child project must have an additional variable `parent_id` within the mutable intake survey

### Default naming: 
- Immutable survey (1) : `Intake`
- Mutable survey (2) : `Mutable Intake`