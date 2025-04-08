import React, {useState, useEffect, useCallback} from "react";
import {
    AppShell,
    Button,
    Pagination,
    Card,
    Group,
    ActionIcon,
    Table,
    Text,
    Title,
    Blockquote,
    Loader,
    Tooltip,
    Space
} from '@mantine/core';
import { useDisclosure } from '@mantine/hooks';
import {useNavigate} from "react-router-dom";
import { AppHeader } from '../../components/AppHeader/appHeader'; // Import the reusable header
import { IconPlus, IconInfoCircle, IconEye, IconQuestionMark, IconLogin2} from '@tabler/icons-react';
import {TableMenu} from "../../components/TableMenu/TableMenu.jsx";

import './dashboard.css';
import DashboardAlert from "../../components/Alerts/dashboardAlert.jsx";

export function Dashboard() {
    const [intakes, setIntakes] = useState([])
    const [error, setError] = useState('')
    const [loading, { toggle, close }] = useDisclosure(true);
    const [newRequestLink, setNewRequestLink] = useState('')
    const navigate = useNavigate()
    const [activePage, setActivePage] = useState(1);
    const [inactivePage, setInactivePage] = useState(1);
    const [alertOpen, setAlertOpen] = useState(true)
    // Sorting
    const [sortColumn, setSortColumn] = useState("ID");
    const [sortDirection, setSortDirection] = useState("desc"); // 'asc' or 'desc'

    useEffect(() => {
        fetchIntakes()
    }, [])

    const fetchIntakes = () => {
        // Real data fetching
        let jsmoModule;
        if (import.meta?.env?.MODE !== 'development')
            jsmoModule = ExternalModules.Stanford.IntakeDashboard;
        jsmoModule.fetchIntakeParticipation(intakeSuccessCallback, intakeErrorCallback);
    };

    const transition = (id) => { navigate(`/detail/${id}`) }

    const intakeSuccessCallback = (res) => {
        console.log('success', res)
        toggle()
        setIntakes(res?.data)
        setNewRequestLink(res?.link)
    }

    const intakeErrorCallback = (err) => {
        console.error('error', err)
        toggle()
        setError(err?.error)
    }

    const toggleActiveCallback = (res) => {
        setIntakes((prevIntakes) => {
            return prevIntakes.map((item) => {
                if (item.intake_id === res?.data?.record_id) {
                    return {
                        ...item,
                        intake_active: res?.data?.intake_active,
                        active_change_date: res?.data?.active_change_date
                    };
                }
                return item;
            });
        });
    }

    const renderNavButton = useCallback(
        (id) => (
            <Button
                id={id}
                onClick={() => transition(id)} // Only created once per id
                className="stanford-button"
                size="xs"
                rightSection={<IconEye size="16" />}
            >
                View
            </Button>
        ),
        [] // Dependencies are empty so this function will only be created once
    );

    const renderMenu = (item) => {
        return (
            <TableMenu
                toggleSuccess={toggleActiveCallback}
                toggleError={toggleActiveCallback}
                rowData={item}
            />
        )
    }

    const truncate = (str, n) => {
        if (!str) return "Not Provided";
        return str.length > n ? str.substring(0, n) + "..." : str;
    };

    // Only enable sorting for these columns
    const sortableColumns = ["ID", "Submission Date", "PI Name"]

    // Mapping of column headers to object keys
    const columnKeyMap = {
        "ID": "intake_id",
        "Submission Date": "completion_timestamp",
        "PI Name": "pi_name"
    }

    // Function to handle sorting when a column header is clicked
    const handleSort = (columnIndex) => {
        if (sortColumn === columnIndex) {
            setSortDirection(sortDirection === "asc" ? "desc" : "asc"); // Toggle sorting order
        } else {
            setSortColumn(columnIndex);
            setSortDirection("asc"); // Default to ascending when switching columns
        }
    }

    // Function to sort table data
    const getSortedData = () => {
        // Sort the entire filtered data (not just the current page)
        if (!sortColumn || !columnKeyMap[sortColumn]) return filteredIntakes; // Return original order if no sorting is applied

        const key = columnKeyMap[sortColumn];
        return [...filteredIntakes].sort((a, b) => {

            let aValue = a[key] ?? "";
            let bValue = b[key] ?? "";

            // Convert numeric fields to numbers for proper sorting
            if (!isNaN(aValue) && !isNaN(bValue)) {
                aValue = Number(aValue);
                bValue = Number(bValue);
                return sortDirection === "asc" ? aValue - bValue : bValue - aValue;
            }

            return sortDirection === "asc"
                ? aValue.toString().localeCompare(bValue.toString())
                : bValue.toString().localeCompare(aValue.toString());
        });
    }

    const chunk = (array, size) => {
        if (!array.length) {
            return [];
        }
        const head = array.slice(0, size);
        const tail = array.slice(size);
        return [head, ...chunk(tail, size)];
    }

    const filteredIntakes = intakes?.filter(item => item.intake_active !== "0") || [];
    const filteredOutIntakes = intakes?.filter(item => item.intake_active === "0") || [];

    // Sort the entire filteredIntakes array first, then chunk it into pages
    const sortedIntakes = getSortedData();  // Sort the entire filtered data
    const pagesActive = chunk(sortedIntakes, 6);  // Chunk sorted data
    const pagesInactive = chunk(filteredOutIntakes, 3);  // Chunk inactive data

    const tableData = {
        head: ['ID', 'Submission Date', 'Study Title', 'PI Name', 'Intake Details', 'More'],
        body: pagesActive[activePage - 1]?.map(item => [
            item?.intake_id,
            item?.completion_timestamp,
            truncate(item?.research_title, 65), // Adjust the number of characters as needed
            item?.pi_name ? item.pi_name : "Not Provided",
            renderNavButton(item.intake_id),
            renderMenu(item),
        ]) || [],
    };

    const finishedTable = {
        head: ['ID', 'Activity Change Date', 'Study Title', 'PI Name', 'Intake Details'],
        body: pagesInactive[inactivePage - 1]?.map(item => [
            item?.intake_id,
            item?.active_change_date,
            truncate(item?.research_title, 65), // Adjust the number of characters as needed
            item?.pi_name ? item.pi_name : "Not Provided",
            renderNavButton(item.intake_id)
        ]) || [],
    };


    return (
        <AppShell
            header={{ height: 55, offset: true}}
            padding="md"
        >
            <AppShell.Header>
                <AppHeader/>
            </AppShell.Header>
            <AppShell.Main
                style={{backgroundColor: 'rgb(248,249,250)'}}
                h="calc(100vh - 55px)" //Prevent UI from scrolling under
            >
                <Title order={3}>Research Intake Dashboard</Title>
                <Text mb="md" c="dimmed">Welcome!</Text>
                <DashboardAlert/>
                {error && <Blockquote mb="md" color="red"><strong>Error: </strong>{error}</Blockquote>}
                <Card id="active-card" withBorder shadow="sm" radius="md">
                    <Card.Section withBorder inheritPadding py="xs">
                        <div style={{display: 'flex', justifyContent: 'space-between', alignItems: 'center'}}>
                            <Group gap="sm">  {/* Group ensures adjacent alignment */}
                                <Text fw={500}>Active Research Projects</Text>
                                {loading && <Loader size={24} />}
                            </Group>
                            <Group justify="flex-end">
                                <Button
                                    className="stanford-button"
                                    rightSection={<IconPlus size={20} />}
                                    component="a"
                                    href={newRequestLink}
                                >New Project</Button>
                            </Group>
                        </div>
                    </Card.Section>
                    <Card.Section className="table-section">
                        <Table className="main-table">
                            <Table.Thead>
                            <Table.Tr>
                                {tableData.head.map((col) => (
                                    <Table.Th
                                        key={col}
                                        onClick={() => sortableColumns.includes(col) && handleSort(col)}
                                        className={`${sortableColumns.includes(col) ? 'sortable-column' : ''} ${sortColumn === col ? "active-column" : ""}`}
                                        style={{ cursor: sortableColumns.includes(col) ? "pointer" : "default"}}
                                    >
                                        {col} {sortColumn === col ? (sortDirection === "asc" ? "↑" : "↓") : sortableColumns.includes(col) ? "⇅" : ""}
                                    </Table.Th>
                                ))}
                            </Table.Tr>
                            </Table.Thead>
                            <Table.Tbody>
                                {tableData.body.length > 0 ? (
                                    tableData.body.map((row, rowIndex) => (
                                        <Table.Tr key={rowIndex}>
                                            {row.map((cell, cellIndex) => (
                                                <Table.Td key={cellIndex}>{cell}</Table.Td>
                                            ))}
                                        </Table.Tr>
                                    ))
                                ) : (
                                    <Table.Tr>
                                        <Table.Td colSpan={tableData.head.length} style={{ textAlign: 'center', padding: '20px' }}>
                                            No active research projects.
                                        </Table.Td>
                                    </Table.Tr>
                                )}
                            </Table.Tbody>
                        </Table>

                    </Card.Section>
                </Card>

                <Group justify="flex-end">
                    <Pagination
                        m="lg"
                        total={pagesActive.length}
                        value={activePage}
                        onChange={setActivePage}
                    />
                </Group>
                {!tableData.body.length && <Space h="xl" />}
                <Card withBorder shadow="sm" radius="md" id="inactive-table">
                    <Card.Section withBorder inheritPadding py="xs">
                        <div style={{display: 'flex', justifyContent: 'space-between', alignItems: 'center'}}>
                            <Group gap="sm">  {/* Group ensures adjacent alignment */}
                                <Text fw={500}>Inactive Research Projects</Text>
                                {loading && <Loader size={24} />}
                                <Tooltip label="List of deactivated intake projects - these entries have no dashboard functionality">
                                    <ActionIcon radius="xl" size="sm" color="rgb(120,0,0)" variant="outline">
                                        <IconQuestionMark stroke={1.5}/>
                                    </ActionIcon>
                                </Tooltip>
                            </Group>
                        </div>
                    </Card.Section>
                    <Card.Section>
                        <Table className="main-table">
                            <Table.Thead>
                                <Table.Tr>
                                    {finishedTable.head.map((col) => (
                                        <Table.Th key={col}>{col}</Table.Th>
                                    ))}
                                </Table.Tr>
                            </Table.Thead>
                            <Table.Tbody>
                                {finishedTable.body.length > 0 ? (
                                    finishedTable.body.map((row, rowIndex) => (
                                        <Table.Tr key={rowIndex}>
                                            {row.map((cell, cellIndex) => (
                                                <Table.Td key={cellIndex}>{cell}</Table.Td>
                                            ))}
                                        </Table.Tr>
                                    ))
                                ) : (
                                    <Table.Tr>
                                        <Table.Td colSpan={finishedTable.head.length} style={{ textAlign: 'center', padding: '20px' }}>
                                            No inactive research projects.
                                        </Table.Td>
                                    </Table.Tr>
                                )}
                            </Table.Tbody>
                        </Table>

                    </Card.Section>
                </Card>
                <Group justify="flex-end">
                    <Pagination
                        total={pagesInactive.length}
                        value={inactivePage}
                        onChange={setInactivePage}
                        m="lg"
                    />
                </Group>
            </AppShell.Main>
        </AppShell>
    )
}
