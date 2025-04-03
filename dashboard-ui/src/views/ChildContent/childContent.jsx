import React, {useState, useEffect, useCallback} from "react";
import {RequestTable} from '../../components/RequestTable/requestTable.jsx';
import {LoadingOverlay, Group, Button, Tooltip, Alert, List, Modal, Badge, Table} from "@mantine/core";
import {IconEye, IconPencil, IconInfoCircle, IconPlus, IconLockOpen2, IconLock, IconMessage2Exclamation} from "@tabler/icons-react";
import {useDisclosure} from "@mantine/hooks";



export function ChildContent({childInfo, immutableParentInfo, mutableParentInfo}) {
    let jsmoModule;
    const [opened, { open, close }] = useDisclosure(false);
    const [childOpened, { open: childOpen, close: childClose }] = useDisclosure(false);
    const [submissions, setSubmissions] = useState([])
    const [loading, setLoading] = useState(true)
    const [childSelected, setChildSelected] = useState(null)

    if (import.meta?.env?.MODE !== 'development')
        jsmoModule = ExternalModules.Stanford.IntakeDashboard;

    useEffect(() => {
        setLoading(true)
        setSubmissions([])

        jsmoModule.getChildSubmissions(
            {'child_pid': childInfo?.child_id, 'universal_id': immutableParentInfo?.record_id},
            (res) => {
                setLoading(false)
                setSubmissions(res?.data || [])
            },
            (err) => {
                console.log(err)
                setLoading(false)
            }
        )
    }, [childInfo, immutableParentInfo])

    const successCallback = (res) => {
        if(res?.url){
            window.open(res.url, "_blank") //redirect to survey
        }
        close();
    }

    const errorCallback = (err) => {
        console.log(err)
        close();
    }

    const onClick = (e) => {
        jsmoModule.newChildRequest({'child_id': childInfo?.child_id, 'universal_id': immutableParentInfo?.record_id}, successCallback, errorCallback)
    }

    const renderInteraction = (data) => {
        if(data?.survey_url){
            if(immutableParentInfo?.intake_active === "0") {
                return <Badge color="red" radius="sm">Canceled</Badge>;
            }

            return (
                <Button
                    size="xs"
                    color="rgb(120,0,0)"
                    variant="outline"
                    component="a"
                    target="_blank"
                    rel="noopener noreferrer"
                    w={80}
                    href={data.survey_url}
                    rightSection={<IconPencil size={16} />}
                >
                    Edit
                </Button>
            )
        } else {
            let findChild = submissions.findIndex(e => e.record_id === data.record_id);
            return (
                <Button
                    size="xs"
                    w={80}
                    className="stanford-button"
                    childIndex = {findChild}
                    rightSection={<IconEye size={16} />}
                    onClick={() => {
                        setChildSelected(findChild)
                        childOpen()
                    }}
                >
                    View
                </Button>
            )
        }
    }

    const createLockTooltip = (num) => {
        if(immutableParentInfo?.intake_active === "0")
            return;

        const incomplete = num === "0"
        const isLocked = ["2", "3", "4", "99"].includes(num);
        return (
            <Tooltip
                w={250}
                multiline
                withArrow
                arrowSize={6}
                label={
                    incomplete
                        ? "This request has not been completed. Please complete the survey by following the edit button on the right and clicking 'Submit'"
                        : isLocked
                            ? "This intake is either currently being worked upon or in a state not accepting changes. Updates made to the unified intake above will not propagate to this request."
                            : "This intake is currently in a state accepting changes. Updates made to the unified intake above will propagate to this request."
                }
            >
                {incomplete ? <IconMessage2Exclamation color="grey" size={24} />
                    : isLocked ? <IconLock color="grey" size={24} />
                        : <IconLockOpen2 color="grey" size={24} />}
            </Tooltip>
        );
    };


    /**
     *
     * @param num
     * @returns {string}
     */
    const createLabelForStatus = (num) => {
        return ({
            "0": "Incomplete",
            "1": "Processing",
            "2": "Processing - Updates Locked",
            "3": "Complete",
            "4": "Unable to Process",
            "5": "Processing - Awaiting additional updates",
            "77": "Received",
            "99": "Canceled"
        }[num] || "Received");
    };

    const renderChildViewModal = () => {
        const tab = {
            caption: 'Survey Details',
            head: ["Label", "Value"],
            body: submissions[childSelected]?.completed_form_pretty
                ? Object.entries(submissions[childSelected]?.completed_form_pretty).map(([key, v]) => [v.label, v.value || ""])
                : [],
        }

        return (
            <Table.ScrollContainer scrollbars="y" h="calc(80vh - 100px)">
                <Table
                    stickyHeader
                    striped
                    data={tab}
                />
            </Table.ScrollContainer>
        );
    }

    let body = submissions.map(e => [
        // createLockTooltip(e?.child_survey_status),
        e.record_id,
        createLabelForStatus(e?.child_survey_status),
        e?.survey_completion_ts ? e.survey_completion_ts : "N/A",
        e?.dashboard_submission_user ? e?.dashboard_submission_user : "N/A" ,
        renderInteraction(e)]
    )

    const renderRequestButton = () => {
        let label

        if(immutableParentInfo?.intake_active === "0") { //If deactivated
            return (
                <Group justify="flex-end">
                    <Tooltip label="This intake is inactive, no requests can be submitted">
                        <Button
                            onClick={e => e.preventDefault()}
                            rightSection={<IconPlus size={20} />}
                            component="a"
                            m="sm"
                            data-disabled
                            disabled
                        >New Request</Button>
                    </Tooltip>
                </Group>
            )
        } else {
            if(mutableParentInfo?.complete !== "2") {
                return (
                    <Group justify="flex-end">
                        <Tooltip label="Please complete universal survey II">
                            <Button
                                onClick={e => e.preventDefault()}
                                rightSection={<IconPlus size={20} />}
                                component="a"
                                m="sm"
                                data-disabled
                                disabled
                            >New Request</Button>
                        </Tooltip>
                    </Group>
                )
            } else {
                return (
                    <Group justify="flex-end">
                        <Button
                            className="stanford-button"
                            onClick={open}
                            rightSection={<IconPlus size={20} />}
                            component="a"
                            m="sm"
                        >New Request</Button>
                    </Group>
                )
            }
        }
    }

    return (
        <div>
            <Modal title={`New Request - ${childInfo?.title}`} size="xl" opened={opened} onClose={close} centered>
                <Alert variant="light" color="blue" title="Notice" icon={<IconInfoCircle/>}>
                    Are you sure you want to create a new request?
                    <List size="sm">
                        <List.Item>Continuing with this process will create a new ticket in the corresponding team's queue</List.Item>
                        <List.Item>New Requests are required if amending a previous request or asking for additional work to be done</List.Item>
                        <List.Item>Please complete any previously submitted surveys before creating additional requests</List.Item>
                    </List>
                </Alert>
                <div style={{display: 'flex', justifyContent: 'center', marginTop: '1rem'}}>
                    <Button
                        onClick={onClick}
                    >Confirm</Button>
                </div>
            </Modal>
            <div>
                <LoadingOverlay visible={loading} loaderProps={{ type: 'dots', size:"md" }} overlayProps={{ blur: 2 }} />
                {renderRequestButton()}
                <Modal style={{maxHeight: '80vh', overflow: 'hidden'}} size="80%" opened={childOpened} onClose={childClose} title="Child Intake Submission">
                    {childOpened && renderChildViewModal()}
                </Modal>
                <RequestTable
                    columns={['Request Number', 'Status', 'Submission Timestamp', 'Submitted By','Survey Link']}
                    body={body}
                />
            </div>
        </div>

    )
}