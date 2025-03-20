import React, { useState, useEffect, useCallback } from "react";
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
    Indicator,
    Breadcrumbs,
    Anchor,
    Stack,
    Title,
    Divider,
    Tooltip, List, Blockquote,
} from "@mantine/core";
import { useDisclosure } from '@mantine/hooks';
import { AppHeader } from "../../components/AppHeader/appHeader";
import { IconBook, IconExternalLink, IconCheck, IconX, IconInfoCircle, IconPencil } from '@tabler/icons-react';
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
    const [pretty, setPretty] = useState([])
    const [currentModalData, setCurrentModalData] = useState([])

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
        setPretty(res?.completed_form_pretty) // Array of two [immutable, mutable]
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
    }, [globalUsername, params]);

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
        const highlight = ["research_title","irb_number", "pta_number", "spo_number", "oncore_number", "account", "mnemonic_number"]
        return (
            <Table.ScrollContainer h={520}>
                <Table stickyHeader striped>
                    <Table.Thead>
                        <Table.Tr>
                            <Table.Th>Label</Table.Th>
                            <Table.Th>Value</Table.Th>
                        </Table.Tr>
                    </Table.Thead>
                    <Table.Tbody>
                        {currentModalData &&
                            Object.entries(currentModalData).map(([key, v]) => {
                                const isHighlighted = highlight.includes(key) && !v.value;
                                const displayValue = v.value || (isHighlighted ? "Missing Value" : "");
                                const isMissing = displayValue === "Missing Value";

                                return (
                                    <Table.Tr
                                        key={key}
                                        style={{ backgroundColor: isHighlighted ? "#ffeb3b" : "inherit" }}
                                    >
                                        <Table.Td>{v.label}</Table.Td>
                                        <Table.Td style={isMissing ? { color: "red", fontWeight: "bold", fontStyle: "italic" } : {}}>
                                            {displayValue}
                                        </Table.Td>
                                    </Table.Tr>
                                );
                            })}
                    </Table.Tbody>
                </Table>
            </Table.ScrollContainer>
        );
    }

    const { overallSteps} = data;

    const renderContent = () => {
        let act = data.find((tab) => {
            return tab.title === activeTab

        })
            return (
                <ChildContent
                    immutableParentInfo={detail}
                    mutableParentInfo={detailMutable}
                    childInfo={act}
                />
            )
    }

    const bottomBox = () => {
        return (
            <Card withBorder shadow="sm" radius="md">
                {/* Right side - Unified Intake Complete Box (larger) */}
                {detailMutable?.complete === "2" && (
                    <Box mb="md" style={{ flex: 1, minWidth: '200px', flexGrow: 2 }}>
                        {/*<Blockquote color="blue" iconSize={36} mt="lg" radius="md" icon={<IconInfoCircle/>}>*/}
                        {/*    <Text mb="sm" fw={700} c="blue">Helpful Tips</Text>*/}
                        {/*    <List size="sm">*/}
                        {/*        <List.Item>The information contained in the survey above will be provided to each request submitted below</List.Item>*/}
                        {/*        <List.Item>Editing the submission above will also update each of the linked requests below as changes are made</List.Item>*/}
                        {/*        <List.Item>Please ensure your Unified Intake details are correct prior to submitting a new request</List.Item>*/}
                        {/*    </List>*/}
                        {/*</Blockquote>*/}
                        <Alert title="Helpful Tips" radius="lg" color="blue" icon={<IconInfoCircle size={24} />}>
                            <List size="sm">
                                <List.Item>The information contained in the survey above will be provided to each request submitted below</List.Item>
                                <List.Item>Editing the submission above will also update each of the linked requests below as changes are made</List.Item>
                                <List.Item>Please ensure your Unified Intake details are correct prior to submitting a new request</List.Item>
                            </List>
                        </Alert>
                    </Box>
                )}
                <Text fw={700} mb="md">SHC Services</Text>
                <Grid gutter="md">
                    {/* Tabs - Services */}
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
                        {data.length && renderContent()}
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
                    <Tooltip disabled={!checkMutableIncomplete()} multiline w={250} position="bottom-start" withArrow label="Please ensure all relevant files are uploaded before submitting a new request below">
                        <Indicator disabled={!checkMutableIncomplete()} inline processing color="red" size={12}>
                            <Button rightSection={<IconBook size={16} />} onClick={() => clickHandler(1)} variant="light" size="xs">View</Button>
                        </Indicator>
                    </Tooltip>
                    <Button
                        color="green"
                        rightSection={<IconPencil size={16} />}
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
    const topBox = () => {
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

    const checkMutableIncomplete = () => {
        const highlight = ["research_title","irb_number", "pta_number", "spo_number", "oncore_number", "account", "mnemonic_number"]
        return pretty[1]
            ? Object.entries(pretty[1]).some(([key, obj]) => (highlight.includes(key) && !obj.value))
            : false;
    }

    const clickHandler = (type) => {
        if(type === 0)
            setCurrentModalData(pretty[0])
        else
            setCurrentModalData(pretty[1])
        if(pretty.length)
            open()
    }


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
                <Group mb="md">
                    <Breadcrumbs>
                        <Anchor onClick={() => navigate("/")}><strong>← Home</strong></Anchor>
                        {/*<Anchor onClick={() => navigate(`/detail/${params.id}`)} >Detail</Anchor>*/}
                    </Breadcrumbs>
                </Group>
                <Group position="apart" align="center" noWrap style={{ width: '100%' }}>
                    {/* Left Section */}
                    <div style={{ flex: 1, minWidth: '300px' }}>
                        <Title order={4}>{detailMutable?.research_title}</Title>
                        <Text c="dimmed">ID: {detail?.record_id}</Text>
                        <Text c="dimmed">{pi}</Text>
                    </div>

                    {/* Right Section - Moved to Right */}
                    <div style={{ flex: 1, minWidth: '250px', textAlign: 'right' }}>
                        <Text c="dimmed" size="sm">Requester Details</Text>
                        {detail?.completion_ts && <Text mb="3px" c="dimmed" size="sm">Submitted {detail?.completion_ts}</Text>}
                        <Button rightSection={<IconBook size={16} />} onClick={() => clickHandler(0)} variant="light" size="xs">View</Button>
                    </div>
                </Group>
                <Divider label="Unified Intake submission" labelPosition="center"  />
                <Modal size="80%" opened={modalOpen} onClose={close} title="Requester Information">
                    {modalOpen && renderTable()}
                </Modal>
                {topBox()}
                <Divider label="SHC Services" labelPosition="center" mb="sm" />
                {bottomBox()}
            </AppShell.Main>
        </AppShell>
    );
}
