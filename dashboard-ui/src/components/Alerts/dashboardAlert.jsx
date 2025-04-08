import { Card, Alert, ActionIcon, List, Transition } from '@mantine/core';
import { IconInfoCircle, IconQuestionMark } from '@tabler/icons-react';
import { useState } from 'react';
import StanfordAlert from "./stanfordAlert.jsx";

function DashboardAlert(){
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
                            <List.Item>
                                These tables represent active and completed research intakes affiliated with your username
                            </List.Item>
                            <List.Item>
                                You will see an entry in either table for any submissions that list your username as a contact
                            </List.Item>
                            <List.Item>
                                Missing a submission? Please contact your PI
                            </List.Item>
                        </List>
                    </Alert>
                </Card>
            </StanfordAlert>

            <StanfordAlert mounted={!alertOpen}>
                <div style={{ display: 'flex', justifyContent: 'flex-end' }}>
                    <ActionIcon
                        mb="md"
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

export default DashboardAlert;