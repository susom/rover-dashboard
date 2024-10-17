import React from "react";
import { Login } from "../../views/Login/login.jsx";
import { Dashboard } from '../../views/Dashboard/dashboard.jsx';

// import { ProtectedRoute } from "../protectedRoute/ProtectedRoute.jsx";
// import { AuthProvider } from "../../Hooks/useAuth.jsx";

import {
    createHashRouter,
    RouterProvider,
} from "react-router-dom";

const router = createHashRouter([
    {
        path: '/',
        element: <Login />
    },
    {
        path: '/dashboard',
        element: (
            // <ProtectedRoute>
                <Dashboard />
            // </ProtectedRoute>
        )
    }
]);

export const DashboardRouter = () => (
    // <AuthProvider>
        <RouterProvider router={router} />
    // </AuthProvider>
);
