import React, {useState, useEffect} from "react";

import {Button, Card, Grid, Group, Text, Stepper} from '@mantine/core';
import {useParams} from "react-router";
import {useNavigate} from "react-router-dom";

export function IntakeDetail() {
    const stepCount = 5; // Change this number to define how many steps you want
    const [activeStep, setActiveStep] = useState(1);
    const [isLoading, setIsLoading] = useState(true);
    const nextStep = () => setActiveStep((current) => (current < stepCount - 1 ? current + 1 : current));
    const prevStep = () => setActiveStep((current) => (current > 0 ? current - 1 : current));
    const params = useParams();
    const navigate = useNavigate()

    useEffect(() => {
        let jsmoModule;

        const successCallback = (res) => {
            console.log('success', res)
            setIsLoading(false)

        }

        const errorCallback = (err) => {
            console.error('error', err)
            navigate('/')
        }

        if(import.meta?.env?.MODE !== 'development')
            jsmoModule = ExternalModules.Stanford.IntakeDashboard

        // globalUsername injected via root.php
        let payload = {username: globalUsername, uid: params['id']}
        jsmoModule.checkUserDetailAccess(payload, successCallback, errorCallback)
    }, [])

    console.log(params)
    // Generate step descriptions dynamically but with the first step being fixed
    const steps = [
        {
            label: 'Universal intake survey',
            description: 'Submitted: Thursday October 17 2024',
            content: 'Content here',
        },
        ...Array.from({ length: stepCount - 1 }, (_, index) => ({
            label: `Step ${index + 2}`,
            description: `This is step ${index + 2}`,
            content: `Step ${index + 2}: Content for this step goes here.`,
        })),
    ];

    if (isLoading) {
        return null; // Render nothing while loading
    }

    return (
        <div style={{width: '100vw', height: '100vh', display: 'flex', flexDirection: 'column', padding: '30px'}}>
            <Grid style={{height: '100%'}}>
                <Grid.Col span={12} style={{padding: 0}}>
                    <Card shadow="sm" p="lg" style={{height: '100%'}}>
                        <Text align="center" size="xl" weight={700}>
                            Welcome to your intake
                        </Text>

                        <Stepper
                            active={activeStep}
                            // onStepClick={setActiveStep}
                            breakpoint="sm"
                            style={{marginTop: '20px'}}
                        >
                            {steps.map((step, index) => (
                                <Stepper.Step key={index} label={step.label} description={step.description}>
                                    Instructions:
                                    <Text>{step.content}</Text>
                                </Stepper.Step>
                            ))}
                        </Stepper>

                        <Group position="center" mt="xl">
                            <Button variant="default" onClick={prevStep} disabled={activeStep === 0}>
                                Back
                            </Button>
                            <Button onClick={nextStep} disabled={activeStep === stepCount - 1}>
                                {activeStep === stepCount - 1 ? 'Finish' : 'Next'}
                            </Button>
                        </Group>
                    </Card>
                </Grid.Col>
            </Grid>
        </div>
    );
}
