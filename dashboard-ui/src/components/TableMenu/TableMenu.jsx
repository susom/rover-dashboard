import {ActionIcon, Menu, Modal, Alert, Button, TextInput, List, Text} from "@mantine/core";
import {IconDots, IconToggleRightFilled, IconToggleLeftFilled, IconDotsCircleHorizontal, IconInfoTriangle} from "@tabler/icons-react";
import { useDisclosure } from '@mantine/hooks';

import React, { useState } from "react";

export function TableMenu({rowData, toggleSuccess, toggleError}){
    const [opened, { open, close }] = useDisclosure(false);
    const [reason, setReason] = useState('')

    function onSuccess(response) {
        console.log('Success:', response);
        close()
        toggleSuccess(response)
    }

    function onError(error) {
        console.error('Error:', error);
        close()
        toggleError(error)
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
                    onClick={open}
                >
                    {isDeactivated ? "Activate" : "Deactivate"}
                </Menu.Item>
                <Menu.Label>{isDeactivated ? inactiveLabel : activeLabel}</Menu.Label>
            </>
        );
    }

    const onInputChange = (e) => setReason(e.currentTarget.value)
    const onSubmit = () => {
        console.log(reason)
        let jsmoModule;
        if (import.meta?.env?.MODE !== 'development')
            jsmoModule = ExternalModules.Stanford.IntakeDashboard;
        jsmoModule.toggleProjectActivation({"uid": rowData?.intake_id, "reason": reason}, onSuccess, onError);
    }
    const icon = <IconInfoTriangle/>
    return (
        <>
            <Modal title="Intake Deactivation" size="xl" opened={opened} onClose={close} centered>
                <Alert variant="light" color="red" title="Warning" icon={icon}>
                    Are you sure you want to deactivate this project?
                    <List size="sm">
                        <List.Item>Deactivating your project will nullify all pending requests affiliated with with this intake</List.Item>
                        <List.Item>You will retain the ability to view all previously submitted requests affiliated with this intake</List.Item>
                        <List.Item>You will be unable to submit new child requests for this main intake</List.Item>
                    </List>
                </Alert>
                <TextInput
                    mt="md"
                    withAsterisk
                    description="Reasoning"
                    placeholder="Funding, timing, etc ..."
                    onChange = {onInputChange}
                />
                <div style={{display: 'flex', justifyContent: 'center', marginTop: '1rem'}}>
                    <Button
                        onClick={onSubmit}
                        disabled={!reason.length}
                    >Confirm</Button>
                </div>
            </Modal>
            <Menu position="right-start" shadow="md" width={220}>
                <Menu.Target>
                    <ActionIcon size="lg" variant="default" aria-label="Settings" style={{border:'none'}}>
                    <IconDots stroke={1.5} />
                </ActionIcon>
            </Menu.Target>
            <Menu.Dropdown>
                {renderActiveItem()}
            </Menu.Dropdown>
        </Menu>
        </>
    )
}