import React, { useState, useEffect } from "react";
import {
    AppShell,
    Card,
    Grid,
    Group,
    Text,
    Stepper,
    Button,
    Stack,
    Title,
    Blockquote,
    List,
    Divider,
} from "@mantine/core";
import { AppHeader } from "../../components/AppHeader/appHeader";
import { IconInfoCircle } from "@tabler/icons-react";

export function IntakeDetail() {
    const [overallStep, setOverallStep] = useState(1);
    const [activeStep, setActiveStep] = useState(1);
    const [isLoading, setIsLoading] = useState(true);
    const [activeTab, setActiveTab] = useState(""); // Track active tab
    const [data, setData] = useState(null); // Stubbed dynamic data

    const nextStep = () =>
        setActiveStep((current) => (current < overallSteps.length - 1 ? current + 1 : current));
    const prevStep = () =>
        setActiveStep((current) => (current > 0 ? current - 1 : current));

    // Stubbed dynamic data
    useEffect(() => {
        console.warn("Using stubbed data for UI testing");

        // Simulating async data fetching
        setTimeout(() => {
            setData({
                projectInfo: {
                    name: "Research Study 1",
                    acronym: "RS1",
                    irbNumber: "123456789",
                    oncoreNumber: "987654321",
                },
                tabs: [
                    { name: "IDS Intake", content: "This is the content for IDS Intake." },
                    { name: "Radiology Intake", content: "Radiology Intake-specific information goes here." },
                    { name: "Lab Intake", content: "Lab Intake-related content goes here." },
                ],
                overallSteps: [
                    "Feasibility",
                    "Pricing",
                    "Docs/Outputs",
                    "Approval",
                    "Finalized",
                ],
                tabLinks: {
                    "IDS Intake": [
                        { label: "Step 1: Complete this intake", completed: true, link: "#" },
                        { label: "Step 2: Upload documents", completed: false, link: "#" },
                        { label: "Step 3: Review approvals", completed: true, link: "#" },
                    ],
                    "Radiology Intake": [
                        { label: "Step 1: Fill intake form", completed: true, link: "#" },
                        { label: "Step 2: Verify patient data", completed: false, link: "#" },
                    ],
                    "Lab Intake": [
                        { label: "Step 1: Initiate lab test", completed: false, link: "#" },
                        { label: "Step 2: Submit samples", completed: true, link: "#" },
                    ],
                },
                user: {
                    username: "irvins",
                    intakesDashboardLink: "/my-intakes-dashboard", // Dynamic link
                },
            });
            setActiveTab("IDS Intake"); // Default to first tab
            setIsLoading(false);
        }, 500); // Simulate async call
    }, []);

    if (isLoading || !data) {
        return (
            <div
                style={{
                    width: "100vw",
                    height: "100vh",
                    display: "flex",
                    justifyContent: "center",
                    alignItems: "center",
                }}
            >
                <Text>Loading...</Text>
            </div>
        );
    }

    const { projectInfo, tabs, overallSteps, tabLinks, user } = data;

    return (
        <AppShell header={{ height: 40 }} padding="md">
            <AppShell.Header>
                <AppHeader />
            </AppShell.Header>
            <AppShell.Main
                style={{
                    backgroundColor: "rgb(248,249,250)",
                    padding: "30px",
                    margin: "30px 0 0 0",
                }}
            >
                {/* Header with button */}
                <Group position="apart" align="center" mb="lg" noWrap>
                    <div>
                        <Title order={3}>Welcome to your intake dashboard</Title>
                        <Text c="dimmed">Logged in as: {user.username}</Text>
                    </div>
                    <Button
                        size="lg"
                        color="blue"
                        radius="md"
                        component="a"
                        href={user.intakesDashboardLink}
                    >
                        My Intakes Dashboard
                    </Button>
                </Group>

                {/* Project Info */}
                <Blockquote
                    color="blue"
                    iconSize={36}
                    mt="lg"
                    radius="md"
                    icon={<IconInfoCircle />}
                >
                    <Text fw={500} mb="xs">
                        Project Info
                    </Text>
                    <List size="sm" spacing="xs">
                        <List.Item>
                            <b>Project Name:</b> {projectInfo.name}
                        </List.Item>
                        <List.Item>
                            <b>Project Acronym:</b> {projectInfo.acronym}
                        </List.Item>
                        <List.Item>
                            <b>IRB#:</b> {projectInfo.irbNumber}
                        </List.Item>
                        <List.Item>
                            <b>Oncore#:</b> {projectInfo.oncoreNumber}
                        </List.Item>
                    </List>
                </Blockquote>

                {/* Overall Stepper */}
                <Card shadow="sm" p="lg" my="lg">
                    <Stepper active={overallStep} onStepClick={setOverallStep}>
                        {overallSteps.map((step, index) => (
                            <Stepper.Step key={index} label={step}>
                                Overall progress for {step}
                            </Stepper.Step>
                        ))}
                    </Stepper>
                </Card>

                <Divider my="md" />

                <Grid style={{ height: "100%" }} gutter="md">
                    {/* Tabs */}
                    <Grid.Col
                        span={3}
                        style={{
                            backgroundColor: "#f8f9fa",
                            padding: "20px",
                            borderRadius: "8px",
                        }}
                    >
                        <Stack spacing="md">
                            {tabs.map((tab) => (
                                <Button
                                    key={tab.name}
                                    variant={activeTab === tab.name ? "filled" : "light"}
                                    fullWidth
                                    onClick={() => setActiveTab(tab.name)}
                                >
                                    {tab.name}
                                </Button>
                            ))}
                        </Stack>
                    </Grid.Col>

                    {/* Tab Content */}
                    <Grid.Col span={9}>
                        <Card shadow="sm" p="lg" style={{ height: "100%" }}>
                            <Text align="center" size="xl" weight={700}>
                                {activeTab}
                            </Text>
                            <Text mt="md">{tabs.find((tab) => tab.name === activeTab)?.content}</Text>
                            <List spacing="sm" mt="lg" size="sm" withPadding>
                                {tabLinks[activeTab]?.map((item, index) => (
                                    <List.Item
                                        key={index}
                                        icon={
                                            item.completed ? (
                                                <input
                                                    type="checkbox"
                                                    checked
                                                    readOnly
                                                    style={{
                                                        pointerEvents: "none",
                                                        marginRight: "10px",
                                                        transform: "scale(1.5)",
                                                    }}
                                                />
                                            ) : (
                                                <input
                                                    type="checkbox"
                                                    style={{
                                                        pointerEvents: "none",
                                                        marginRight: "10px",
                                                        transform: "scale(1.5)",
                                                    }}
                                                />
                                            )
                                        }
                                    >
                                        <a
                                            href={item.link}
                                            style={{ textDecoration: "none", color: "inherit" }}
                                        >
                                            {item.label}
                                        </a>
                                    </List.Item>
                                ))}
                            </List>
                        </Card>
                    </Grid.Col>
                </Grid>
            </AppShell.Main>
        </AppShell>
    );
}
