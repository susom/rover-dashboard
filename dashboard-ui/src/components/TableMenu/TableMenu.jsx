import {ActionIcon, Menu} from "@mantine/core";
import {IconDotsVertical, IconToggleRightFilled, IconToggleLeftFilled} from "@tabler/icons-react";

import React from "react";

// Default success handler
function defaultSuccess(response) {
    console.log('Success:', response);
}

// Default error handler
function defaultError(error) {
    console.error('Error:', error);
}

export function TableMenu({rowData, toggleSuccess = defaultSuccess, toggleError = defaultError}){

    const toggle = () => {
        let jsmoModule;
        if (import.meta?.env?.MODE !== 'development')
            jsmoModule = ExternalModules.Stanford.IntakeDashboard;
        jsmoModule.toggleProjectActivation({"uid": rowData?.intake_id}, toggleSuccess, toggleError);
    }

    const renderActiveItem = () => {
        const isDeactivated = rowData?.intake_active === "0";
        const activeLabel = "Restricts functionality & editing You can reactivate at any time"
        const inactiveLabel = "Enables detail page navigation & editing"

        return (
            <>
                <Menu.Item
                    color={isDeactivated ? "green" : "red"}
                    leftSection={isDeactivated ? <IconToggleLeftFilled /> : <IconToggleRightFilled />}
                    onClick={toggle}
                >
                    {isDeactivated ? "Activate" : "Deactivate"}
                </Menu.Item>
                <Menu.Label>{isDeactivated ? inactiveLabel : activeLabel}</Menu.Label>
            </>
        );
    };


    return (
        <Menu position="right-start" shadow="md" width={220}>
            <Menu.Target>
                <ActionIcon size="lg" variant="default" aria-label="Settings" style={{border:'none'}}>
                    <IconDotsVertical stroke={1.5} />
                </ActionIcon>
            </Menu.Target>
            <Menu.Dropdown>
                {renderActiveItem()}
            </Menu.Dropdown>
        </Menu>
    )
}