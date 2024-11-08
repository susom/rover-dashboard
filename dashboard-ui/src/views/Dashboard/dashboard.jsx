import React, {useState, useEffect} from "react";
import {Button, Card, Grid, Table, Text} from '@mantine/core';

export function Dashboard() {
    const [intakes, setIntakes] = useState([])

    useEffect(() => {
        console.log('fire?')
        fetchIntakes()
    }, [])

    const fetchIntakes = () => {
        let jsmoModule;
        if(import.meta?.env?.MODE !== 'development')
            jsmoModule = ExternalModules.Stanford.IntakeDashboard
        jsmoModule.fetchIntakeParticipation(successCallback)

    }

    const successCallback = (res) => {
        console.log('success', res)
        setIntakes(res?.data)
    }

    const tableData = {
        caption: 'Intake List',
        head: ['UID', 'Listed Contact Type', 'Study Title', 'PI Name', 'Completion Status', 'Detail'],
        body: (intakes && intakes.length > 0) ? intakes.map(item => [item.intake_id, item.type, item.research_title, item.pi_name, item.intake_complete, "Detail"]) : []
    }

    return (
        <div style={{width: '100vw', height: '100vh', display: 'flex', flexDirection: 'column', padding: '30px'}}>
            <Grid style={{height: '100%'}}>
                <Grid.Col span={12} style={{padding: 0}}>
                    <Card shadow="sm" p="lg" style={{height: '100%'}}>
                        <Text align="center" size="xl" weight={700}>
                            Welcome to your intake dashboard!
                        </Text>
                        <Table
                            data={tableData}
                        />
                    </Card>
                </Grid.Col>
            </Grid>
        </div>
    );
}
