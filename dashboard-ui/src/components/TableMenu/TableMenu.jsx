import {ActionIcon, Menu, Modal, Alert, Button, TextInput, List, Radio, Text, Stack, Group} from "@mantine/core";
import {IconDots, IconToggleRightFilled, IconToggleLeftFilled, IconDotsCircleHorizontal, IconInfoTriangle} from "@tabler/icons-react";
import { useDisclosure } from '@mantine/hooks';
import React, { useState } from "react";
import "./TableMenu.css";

// Dashboard deactivation menu
export function TableMenu({rowData, toggleSuccess, toggleError}){
    const [opened, { open, close }] = useDisclosure(false);
    const [reason, setReason] = useState('')
    const [radioValue, setRadioValue] = useState(null)
    function onSuccess(response) {
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
        let jsmoModule;
        if (import.meta?.env?.MODE !== 'development')
            jsmoModule = ExternalModules.Stanford.IntakeDashboard;

        let payload = reason ? reason : radioValue // Reason will only be valid if "Other" is selected, otherwise take radio value
        jsmoModule.toggleProjectActivation({"uid": rowData?.intake_id, "reason": payload}, onSuccess, onError);
    }

    const radioData = [
        { name: 'Funding', description: 'Lack of proper funds, etc'},
        { name: 'Priority', description: 'Prioritization reasons, other projects' },
        { name: 'Other', description: 'Other reasons why deactivating is necessary' },
    ];

    const cards = radioData.map((item) => (
        <Radio.Card className="tmRoot"  radius="md" value={item.name} key={item.name}>
            <Group wrap="nowrap" align="flex-start">
                <Radio.Indicator />
                <div>
                    <Text className="tmLabel">{item.name}</Text>
                    <Text className="tmDescription" >{item.description}</Text>
                </div>
            </Group>
        </Radio.Card>
    ));

    return (
        <>
            <Modal title="Intake Deactivation" size="xl" opened={opened} onClose={close} centered>
                <Alert variant="light" color="red" title="Warning" icon={<IconInfoTriangle/>}>
                    Are you sure you want to deactivate this project?
                    <List size="sm">
                        <List.Item>Deactivating your project will nullify all pending requests affiliated with with this intake</List.Item>
                        <List.Item>You will retain the ability to view all previously submitted requests affiliated with this intake</List.Item>
                        <List.Item>You will be unable to submit new child requests for this main intake</List.Item>
                    </List>
                </Alert>

                <Radio.Group
                    mt="md"
                    value={radioValue}
                    onChange={setRadioValue}
                    label="Choose a reason for deactivating this intake project:"
                    // description="Choose a reason for deactivating this intake project:"
                >
                    <Stack pt="md" gap="xs">
                        {cards}
                    </Stack>
                </Radio.Group>

                {radioValue === "Other" &&
                    <TextInput
                        mt="md"
                        withAsterisk
                        label="Please provide other reasoning here:"
                        placeholder="..."
                        onChange = {onInputChange}
                    />
                }

                <div style={{display: 'flex', justifyContent: 'center', marginTop: '1rem'}}>
                    <Button
                        onClick={onSubmit}
                        disabled={radioValue === null || (radioValue === "Other" && !reason.length)}
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