import React, { useState, useEffect } from "react";
import {
    AppShell,
    Card,
    Grid,
    Group,
    Text,
    Table,
    Flex,
    Box,
    Modal,
    Button,
    Alert,
    Timeline,
    Stack,
    Title,
    Divider, Tooltip,
} from "@mantine/core";
import { useDisclosure } from '@mantine/hooks';
import { AppHeader } from "../../components/AppHeader/appHeader";
import { IconBook, IconExternalLink, IconCheck, IconX, IconInfoCircle } from '@tabler/icons-react';
import {useNavigate, useParams} from "react-router-dom";
import {ChildContent} from "../ChildContent/childContent.jsx";

export function IntakeDetail() {
    const [overallStep, setOverallStep] = useState(1);
    const [activeStep, setActiveStep] = useState(1);
    const [isLoading, setIsLoading] = useState(true);
    const [activeTab, setActiveTab] = useState(""); // Track active tab
    const [data, setData] = useState([]); // Stubbed dynamic data
    const [detail, setDetail] = useState("");
    const [detailMutable, setDetailMutable] = useState("")
    const [mutableUrl, setmutableUrl] = useState("")
    const params = useParams()
    const navigate = useNavigate();
    const [modalOpen, { open, close }] = useDisclosure(false);
    const [pretty, setPretty] = useState("")

    const nextStep = () =>
        setActiveStep((current) => (current < overallSteps.length - 1 ? current + 1 : current));
    const prevStep = () =>
        setActiveStep((current) => (current > 0 ? current - 1 : current));

    const successCallback = (res) => {
        setData(res?.surveys || [])
        if (res?.surveys?.[0]?.title) {
            setActiveTab(res.surveys[0].title); // Default to first tab
        }
        setDetail(res?.completed_form_immutable)
        setDetailMutable(res?.completed_form_mutable)
        setPretty(res?.completed_form_pretty)
        setmutableUrl(res?.mutable_url)
        setIsLoading(false);
    }

    const errorCallback = (err) => {
        console.error('error', err)
        navigate('/')
    }

    // Stubbed dynamic data
    useEffect(() => {

        let jsmoModule;
        if (import.meta?.env?.MODE !== 'development')
            jsmoModule = ExternalModules.Stanford.IntakeDashboard;

        jsmoModule.getUserDetail({'username': globalUsername, 'uid': params?.id}, successCallback, errorCallback)


        // Simulating async data fetching
        // setTimeout(() => {
        //     setData({
        //         projectInfo: {
        //             name: "Research Study 1",
        //             acronym: "RS1",
        //             irbNumber: "123456789",
        //             oncoreNumber: "987654321",
        //         },
        //         tabs: [
        //             { name: "IDS Intake", content: "This is the content for IDS Intake." },
        //             { name: "Radiology Intake", content: "Radiology Intake-specific information goes here." },
        //             { name: "Lab Intake", content: "Lab Intake-related content goes here." },
        //         ],
        //         overallSteps: [
        //             "Feasibility",
        //             "Pricing",
        //             "Docs/Outputs",
        //             "Approval",
        //             "Finalized",
        //         ],
        //         tabLinks: {
        //             "IDS Intake": [
        //                 { label: "Step 1: Complete this intake", completed: true, link: "#" },
        //                 { label: "Step 2: Upload documents", completed: false, link: "#" },
        //                 { label: "Step 3: Review approvals", completed: true, link: "#" },
        //             ],
        //             "Radiology Intake": [
        //                 { label: "Step 1: Fill intake form", completed: true, link: "#" },
        //                 { label: "Step 2: Verify patient data", completed: false, link: "#" },
        //             ],
        //             "Lab Intake": [
        //                 { label: "Step 1: Initiate lab test", completed: false, link: "#" },
        //                 { label: "Step 2: Submit samples", completed: true, link: "#" },
        //             ],
        //         },
        //         user: {
        //             username: "irvins",
        //             intakesDashboardLink: "/my-intakes-dashboard", // Dynamic link
        //         },
        //     });
        //     setActiveTab("IDS Intake"); // Default to first tab
        //     setIsLoading(false);
        // }, 500); // Simulate async call
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

    const renderTable = () => {
        const tab = {
            caption: 'Survey Details',
            head: ["Label", "Value"],
            body: pretty
                ? Object.entries(pretty).map(([key, v]) => [v.label, v.value || ""])
                : [],
        }

        return (
            <Table.ScrollContainer h={520}>
                <Table
                    stickyHeader
                    striped
                    data={tab}
                />
            </Table.ScrollContainer>
        );
    }

    const { overallSteps} = data;

    const renderContent = () => {
        let act = data.find((tab) => {
            return tab.title === activeTab

        })
            return (
                <>
                    <ChildContent
                        immutableParentInfo={detail}
                        mutableParentInfo={detailMutable}
                        childInfo={act}
                    />
                </>
            )
    }

    const renderChildSurveys = () => {
            return (
                <Card withBorder shadow="sm" radius="md">
                    {/* Right side - Unified Intake Complete Box (larger) */}
                    {detailMutable?.complete === "2" && (
                        <Box mb="md" style={{ flex: 1, minWidth: '200px', flexGrow: 2 }}>
                            <Alert radius="lg" variant="outline" color="blue" icon={<IconInfoCircle size={24} />}>
                                <Text size="sm" fw={500}>The information contained in the above surveys will be provided to each of the teams below when creating new requests.</Text>
                                <Text size="sm" fw={500}>Editing the above submission will also update each of the linked requests below as changes are made.</Text>
                                <Text size="sm" fw={500}>Please ensure your Unified Intake details are correct prior to submitting a new request</Text>
                                {/*<List>*/}
                                {/*    <List.Item><Text size="sm" fw={500}>The information contained in the above surveys will be provided to each of the teams below when creating new requests.</Text></List.Item>*/}
                                {/*    <List.Item><Text size="sm" fw={500}>Editing the above submission will also update each of the linked requests below as changes are made.</Text></List.Item>*/}
                                {/*</List>*/}
                            </Alert>
                        </Box>
                    )}
                    <Grid gutter="md">
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
                                {data?.map((tab) => (
                                    <Button
                                        key={tab?.title}
                                        variant={activeTab === tab.title ? "filled" : "light"}
                                        fullWidth
                                        onClick={() => setActiveTab(tab?.title)}
                                    >
                                        {tab?.title}
                                    </Button>
                                ))}
                            </Stack>
                        </Grid.Col>

                        {/* Tab Content */}
                        <Grid.Col span={9}>
                            {/*<Card shadow="sm" p="lg">*/}
                            {/*    <Card.Section>*/}
                                    {data.length && renderContent()}
                            {/*    </Card.Section>*/}
                            {/*</Card>*/}
                        </Grid.Col>
                    </Grid>
                </Card>
            )
    }

    const renderMutableSection = () => {
        if (detail?.intake_active === "0") {
            return (
                <>
                    <Text c="red" fw={700} size="sm">Intake Inactive: </Text>
                    <Tooltip label="Intake inactive - Functionality Disabled">
                        <Button
                            disabled
                            onClick={e => e.preventDefault()}
                            color="red"
                            rightSection={<IconExternalLink size={16} />}
                            component="a"
                            href={mutableUrl}
                            variant="light"
                            size="xs"
                        >
                            Complete
                        </Button>
                    </Tooltip>
                </>
            );
        }

        return detailMutable?.complete !== "2" ? (
            <>
                <Text c="red" fw={700} size="sm">Submission II Incomplete: </Text>
                <Button
                    disabled={detail?.intake_active === "0"}
                    color="red"
                    rightSection={<IconExternalLink size={16} />}
                    component="a"
                    href={mutableUrl}
                    variant="light"
                    size="xs"
                >
                    Complete
                </Button>
            </>
        ) : (
            <>
                <Text c="dimmed" size="sm">View or Edit prior survey submission:</Text>
                <Button
                    color="green"
                    rightSection={<IconExternalLink size={16} />}
                    component="a"
                    href={mutableUrl}
                    variant="light"
                    size="xs"
                >
                    Edit
                </Button>
            </>
        );
    };
    const card1 = () => {
        return (
            <Card withBorder shadow="sm" radius="md" my="sm">
                <Flex justify="space-between" align="flex-start">
                    <Box style={{ flex: 1 , minWidth: '350px'}}>
                        <Timeline active={1} lineWidth={3} bulletSize={24}>
                            <Timeline.Item
                                bullet={detailMutable?.complete === "2" && detail?.intake_active !== "0" ? <IconCheck size={16} /> : <IconX size={16} />}
                                title="Unified Intake Details"
                                lineVariant="dashed"
                                color={detailMutable?.complete === "2" && detail?.intake_active !== "0" ? "green" : "red"} // Change color dynamically
                            >
                                {detailMutable?.complete === "2" && detail?.intake_active !== "0" &&
                                    <Text c="dimmed" size="sm">Last Edit: {detailMutable?.completion_ts} {detailMutable?.last_editing_user ? ` by ${detailMutable?.last_editing_user}`: ''}</Text>
                                }
                                {detailMutable?.complete !== "2" && detail?.intake_active !== "0" &&
                                    <Text c="dimmed" size="sm">Please complete all required fields on the survey and click submit</Text>
                                }
                                <Group spacing="xs" align="center">
                                    {renderMutableSection()}
                                </Group>
                            </Timeline.Item>
                        </Timeline>
                    </Box>
                </Flex>
            </Card>
        )
    }

    const pi = detailMutable?.pi_f_name ? `Principal Investigator: ${detailMutable?.pi_f_name} ${detailMutable?.pi_l_name}` : '';

    return (
        <AppShell
            padding="md"
            header={{ height: 55, offset: true}}
        >
            <AppShell.Header>
                <AppHeader />
            </AppShell.Header>
            <AppShell.Main
                h="calc(100vh - 55px)" //Prevent UI from scrolling under
                style={{
                    backgroundColor: "rgb(248,249,250)",
                }}
            >
                {/* Header with button */}
                <Group position="apart" align="center" noWrap style={{ width: '100%' }}>
                    {/* Left Section */}
                    <div style={{ flex: 1, minWidth: '300px' }}>
                        <Title order={4}>{detailMutable?.research_title}</Title>
                        <Text c="dimmed">UID #{detail?.record_id}</Text>
                        <Text c="dimmed">{pi}</Text>
                    </div>

                    {/* Right Section - Moved to Right */}
                    <div style={{ flex: 1, minWidth: '250px', textAlign: 'right' }}>
                        <Title order={4}>Requester Details</Title>
                        {detail?.completion_ts && <Text mb="3px" c="dimmed" size="sm">Submitted {detail?.completion_ts}</Text>}
                        <Button rightSection={<IconBook size={16} />} onClick={open} variant="light" size="xs">View</Button>
                    </div>
                </Group>
                <Divider label="Unified Intake submission" labelPosition="center"  />
                <Modal size="80%" opened={modalOpen} onClose={close} title="Requester Information">
                    {modalOpen && renderTable()}
                </Modal>
                {card1()}
                <Divider label="SHC Services" labelPosition="center" mb="sm" />
                {renderChildSurveys()}
            </AppShell.Main>
        </AppShell>
    );
}
