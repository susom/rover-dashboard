import { Card, Alert, ActionIcon, List, Transition } from '@mantine/core';
import { IconInfoCircle, IconQuestionMark } from '@tabler/icons-react';
import React, { useState } from 'react';
import StanfordAlert from "./stanfordAlert.jsx";

function IntakeAlert(){
    const [alertOpen, setAlertOpen] = useState(true);
    const toggleAlert = () => setAlertOpen((prev) => !prev);

    return (
        <>
            <StanfordAlert mounted={alertOpen}>
                <Card withBorder shadow="sm" radius="md" mb="md">
                    <Alert
                        title="Helpful Tips"
                        withCloseButton
                        onClose={toggleAlert}
                        variant="transparent"
                        radius="lg"
                        className="stanford-alert"
                        icon={<IconInfoCircle size={24} />}
                    >
                        <List size="sm">
                            <List.Item>The information contained in the survey above will be provided to each request submitted below</List.Item>
                            <List.Item>Editing the submission above will also update each of the linked requests below as changes are made</List.Item>
                            <List.Item>Please ensure your Unified Intake details are correct prior to submitting a new request</List.Item>
                        </List>
                    </Alert>
                </Card>
            </StanfordAlert>

            <StanfordAlert mounted={!alertOpen}>
                <div style={{ display: 'flex', justifyContent: 'flex-end' }}>
                    <ActionIcon
                        radius="xl"
                        size="sm"
                        color="rgb(120,0,0)"
                        variant="outline"
                        onClick={toggleAlert}
                    >
                        <IconQuestionMark stroke={1.5} />
                    </ActionIcon>
                </div>
            </StanfordAlert>
        </>
    );
}

export default IntakeAlert;