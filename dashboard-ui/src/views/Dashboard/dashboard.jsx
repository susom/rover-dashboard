import React, {useState} from "react";
import {Button, Card, Grid, Group, Text, Stepper} from '@mantine/core';

export function Dashboard() {
    return (
        <div style={{width: '100vw', height: '100vh', display: 'flex', flexDirection: 'column', padding: '30px'}}>
            <Grid style={{height: '100%'}}>
                <Grid.Col span={12} style={{padding: 0}}>
                    <Card shadow="sm" p="lg" style={{height: '100%'}}>
                        <Text align="center" size="xl" weight={700}>
                            Welcome to your intake dashboard!
                        </Text>
                    </Card>
                </Grid.Col>
            </Grid>
        </div>
    );
}
