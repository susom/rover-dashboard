import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
// import './index.css'
import '@mantine/core/styles.css';
import { MantineProvider } from '@mantine/core';
import {DashboardRouter} from "./components/DashboardRouter/DashboardRouter.jsx";

createRoot(document.getElementById('root')).render(
  <StrictMode>
      <MantineProvider>
        <DashboardRouter />
      </MantineProvider>
  </StrictMode>,
)
