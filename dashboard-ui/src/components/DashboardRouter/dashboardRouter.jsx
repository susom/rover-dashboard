import React from "react";
import { Login } from "../../views/Login/login.jsx";
import { Dashboard } from '../../views/Dashboard/dashboard.jsx';
import { IntakeDetail } from '../../views/IntakeDetail/intakeDetail.jsx';
import { Error } from "../../views/Error/error.jsx";
// import { ProtectedRoute } from "../protectedRoute/ProtectedRoute.jsx";
// import { AuthProvider } from "../../Hooks/useAuth.jsx";

import {
    createHashRouter,
    RouterProvider,
} from "react-router-dom";

const router = createHashRouter([
    {
        path: '/',
        element: <Dashboard />,
        errorElement: <Error/>
    },
    {
        path: '/detail/:id',
        element: (
            <IntakeDetail />
            // <ProtectedRoute>
            // </ProtectedRoute>
        )
    }
]);

export const DashboardRouter = () => (
    // <AuthProvider>
        <RouterProvider router={router} />
    // </AuthProvider>
);
