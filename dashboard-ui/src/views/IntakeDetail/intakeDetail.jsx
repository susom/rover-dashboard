import React, { useState, useEffect } from "react";
import {
    AppShell,
    Card,
    Grid,
    Group,
    Text,
    Table,
    Modal,
    Button,
    Alert,
    Badge,
    Timeline,
    Stack,
    Title,
    Blockquote,
    List,
    Divider, Tooltip,
} from "@mantine/core";
import { useDisclosure } from '@mantine/hooks';
import { AppHeader } from "../../components/AppHeader/appHeader";
import { IconInfoCircle } from "@tabler/icons-react";
import { IconBook, IconExternalLink, IconCheck } from '@tabler/icons-react';
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
            head: ['Variable', "Label", "Value"],
            body: pretty
                ? Object.entries(pretty).map(([key, v]) => [key, v.label, v.value || ""])
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
                        <Card shadow="sm" p="lg">
                            <Card.Section>
                                {data.length && renderContent()}
                            </Card.Section>
                        </Card>
                    </Grid.Col>
                </Grid>
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
                <Group position="apart" align="center" mb="lg" noWrap style={{ width: '100%' }}>
                    <div>
                        <Title order={3}>Intake Detail</Title>
                        <Text c="dimmed">Logged in as: {globalUsername}</Text>
                    </div>
                    <Button
                        size="sm"
                        color="blue"
                        radius="md"
                        component="a"
                        onClick={() => navigate('/')}
                        style={{ marginLeft: 'auto' }} // Ensure it aligns properly
                    >
                        Intake Home
                    </Button>
                </Group>

                {/* Project Info */}
                <Blockquote
                    color={detail?.intake_active === "0" ? "red" : "blue"}
                    iconSize={36}
                    mt="lg"
                    radius="md"
                    icon={<IconInfoCircle />}
                >
                    {detail?.intake_active === "0" &&
                        <>
                            <Group spacing="xs" align="center" mt="xs">
                                <Text fw={700}>Intake Inactive</Text>
                            </Group>
                            <Group spacing="xs" align="center" mt="xs">
                                <Text size="sm" c="dimmed">Intake deactivated by user:</Text>
                                <Text size="sm" fw={700}>{detail?.deactivation_user}</Text>
                                <Text size="sm" c="dimmed">on</Text>
                                <Text size="sm" fw={700}>{detail?.active_change_date}</Text>
                            </Group>
                            <Group spacing="xs" align="center" mt="xs">
                                <Text size="sm" c="dimmed">Reason:</Text>
                                <Text size="sm" fw={700}>{detail?.deactivation_reason}</Text>
                            </Group>
                            <Divider mt="sm" mb="sm" size="sm" />
                        </>
                    }

                    <Group spacing="xs" align="center" mt="xs">
                        <Text size="sm" c="dimmed">Project Name:</Text>
                        <Text size="sm" fw={700}>{detailMutable?.research_title}</Text>
                    </Group>
                    <Group spacing="xs" align="center" mt="xs">
                        <Text size="sm" c="dimmed">Universal ID #:</Text>
                        <Text size="sm" fw={700}>{detail?.record_id}</Text>
                    </Group>
                    <Group spacing="xs" align="center" mt="xs">
                        <Text size="sm" c="dimmed">Principal Investigator:</Text>
                        <Text size="sm" fw={700}>{`${detailMutable?.pi_f_name} ${detailMutable?.pi_l_name}`}</Text>
                    </Group>
                </Blockquote>
                <Divider label="Universal Intake submissions" labelPosition="center" my="md" />
                <Modal size="80%" opened={modalOpen} onClose={close} title="Universal Intake Submission I">
                    {modalOpen && renderTable()}
                </Modal>
                <Card shadow="sm" p="lg" my="lg">
                    <Timeline active={1} lineWidth={3} bulletSize={24}>
                        <Timeline.Item bullet={<IconCheck size={16} />} title="Universal Intake submission I">
                            <Text c="dimmed" size="sm">Submitted {detail?.completion_ts}</Text>
                            <Group spacing="xs" align="center">
                                <Text c="dimmed" size="sm">View prior survey submission:</Text>
                                <Button rightSection={<IconBook size={16} />} onClick={open} variant="light" size="xs">View</Button>
                            </Group>
                        </Timeline.Item>
                        <Timeline.Item bullet={detailMutable?.complete === "2" ? <IconCheck size={16} /> : ''} title="Universal Intake submission II" lineVariant="dashed">
                            {detailMutable?.complete !== "2" && detail?.intake_active !== "0" &&
                                <Text c="dimmed" size="sm">Please complete all required fields on the survey and click submit</Text>
                            }
                            <Group spacing="xs" align="center">
                                {renderMutableSection()}
                            </Group>
                        </Timeline.Item>
                        {/*<Timeline.Item title="Complete additional surveys required for requested services">*/}
                        {/*    <Text c="dimmed" size="sm">Please complete each intake for the requested services below</Text>*/}
                        {/*</Timeline.Item>*/}
                    </Timeline>
                    {detailMutable?.complete === "2" &&
                        <Alert mt="md" variant="light" color="green" title="Universal Surveys Complete!" icon={<IconCheck size={24} />}>
                            <List>
                                <List.Item><Text size="sm" fw={500}>Thank you for completing Intake Surveys I and II!</Text></List.Item>
                                <List.Item><Text size="sm" fw={500}>The information contained in the above surveys will be provided to each of the teams below when creating new requests</Text></List.Item>
                                <List.Item><Text size="sm" fw={500}>Editing Submission II will also update each of the linked requests below as changes are made</Text></List.Item>
                            </List>

                        </Alert>
                    }


                </Card>

                <Divider label="Services" labelPosition="center" my="md" />
                {renderChildSurveys()}
            </AppShell.Main>
        </AppShell>
    );
}
