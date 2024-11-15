import React, {useState, useEffect, useCallback} from "react";
import {AppShell, Button, Group, Card, Image, Table, Text, Divider, Title, Blockquote, List, Loader} from '@mantine/core';
import { useDisclosure } from '@mantine/hooks';
import { IconInfoCircle } from '@tabler/icons-react';
import {useNavigate} from "react-router-dom";
import './dashboard.css';

export function Dashboard() {
    const [intakes, setIntakes] = useState([])
    const [error, setError] = useState('')
    const [loading, { toggle, close }] = useDisclosure(true);

    const navigate = useNavigate()
    console.log(loading)
    useEffect(() => {
        fetchIntakes()
    }, [])

    const fetchIntakes = () => {
        let jsmoModule;
        if(import.meta?.env?.MODE !== 'development')
            jsmoModule = ExternalModules.Stanford.IntakeDashboard
        jsmoModule.fetchIntakeParticipation(successCallback, errorCallback)

    }

    const successCallback = (res) => {
        console.log('success', res)
        setIntakes(res?.data)
    }

    const errorCallback = (err) => {
        console.error('error', err)
        setError(err?.error)
    }

    const transition = (id) => {
        navigate(`/detail/${id}`)
    }

    const renderNavButton = useCallback(
        (id) => (
            <Button
                id={id}
                onClick={() => transition(id)} // Only created once per id
                variant="light"
            >
                Navigate
            </Button>
        ),
        [] // Dependencies are empty so this function will only be created once
    );

    const tableData = {
        caption: 'List of intakes you have been added to',
        head: ['UID', 'Listed Contact Type', 'Study Title', 'PI Name', 'Completion Status', 'Detail'],
        body: (intakes && intakes.length > 0) ? intakes.map(item => [item.intake_id, item.type, item.research_title, item.pi_name, item.intake_complete, renderNavButton(item.intake_id)]) : []
    }

    const finishedTable = {
        caption: 'List of completed intakes',
        head: ['UID', 'Listed Contact Type', 'Study Title', 'PI Name', 'Completion Status', 'Detail'],
        body: (intakes && intakes.length > 0) ? intakes.map(item => [item.intake_id, item.type, item.research_title, item.pi_name, item.intake_complete, renderNavButton(item.intake_id)]) : []
    }

    return (
        <AppShell
            header={{ height: 40 }}
            padding="md"
        >
            <AppShell.Header>
                <Group h="100%" px="md">
                    <Image src="https://storage.googleapis.com/group-chat-therapy/stanford-logo.svg"
                           h={30}
                           w="auto"
                           fit="contain"
                           alt="stanford_image"
                    />

                </Group>
            </AppShell.Header>
            <AppShell.Main style={{backgroundColor: 'rgb(248,249,250)'}}>
                <Title order={3}>Welcome to your intake dashboard</Title>
                <Text c="dimmed">Logged in as: {globalUsername}</Text>
                <Blockquote color="blue" iconSize={36} mt="xl" radius="md" icon={<IconInfoCircle/>}>
                    <List size="md">
                        <List.Item>These tables represent active and completed research intakes affiliated with your username</List.Item>
                        <List.Item>You will see an entry in either table for any submissions that list your username as a contact</List.Item>
                        <List.Item>Missing a submission? Please contact your PI</List.Item>
                    </List>
                </Blockquote>
                <Divider my="md" />
                <Card withBorder shadow="sm" radius="md">
                    <Card.Section withBorder inheritPadding py="xs">
                        <div style={{display: 'flex', justifyContent: 'space-between', alignItems: 'center'}}>
                            <Text fw={500}>Active</Text>
                            {loading && <Loader size={32} />}
                        </div>
                    </Card.Section>
                    <Card.Section>
                        <Table
                            striped
                            data={tableData}
                        />
                    </Card.Section>
                </Card>

                <div style={{marginTop: '100px'}}></div>

                <Card withBorder shadow="sm" radius="md">
                    <Card.Section withBorder inheritPadding py="xs">
                        <div style={{display: 'flex', justifyContent: 'space-between', alignItems: 'center'}}>
                            <Text fw={500}>Complete</Text>
                            {loading && <Loader size={32} />}
                        </div>
                    </Card.Section>
                    <Card.Section>
                        <Table
                            striped
                            data={finishedTable}
                        />
                    </Card.Section>
                </Card>

            </AppShell.Main>
        </AppShell>

    )
    // return (
    //     <div style={{width: '100vw', height: '100vh', display: 'flex', flexDirection: 'column', padding: '30px'}}>
    //         <Grid style={{height: '100%'}}>
    //             <Grid.Col span={12} style={{padding: 0}}>
    //                 <Card shadow="sm" p="lg" style={{height: '100%'}}>
    //                     <Text align="center" size="xl" weight={700}>
    //                         Welcome to your intake dashboard!
    //                     </Text>
    //                     <Table
    //                         striped
    //                         data={tableData}
    //                     />
    //                 </Card>
    //             </Grid.Col>
    //         </Grid>
    //     </div>
    // );
}
