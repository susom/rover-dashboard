import React, {useState, useEffect, useCallback} from "react";
import {
    AppShell,
    Button,
    Pagination,
    Card,
    ActionIcon,
    Table,
    Text,
    Divider,
    Title,
    Blockquote,
    List,
    Loader,
    Tooltip,
    rem
} from '@mantine/core';
import { useDisclosure } from '@mantine/hooks';
import {useNavigate} from "react-router-dom";
import { AppHeader } from '../../components/AppHeader/appHeader'; // Import the reusable header
import { IconPlus, IconInfoCircle, IconArrowRight, IconQuestionMark, IconLogin2} from '@tabler/icons-react';
import {TableMenu} from "../../components/TableMenu/TableMenu.jsx";

import './dashboard.css';

export function Dashboard() {
    const [intakes, setIntakes] = useState([])
    const [error, setError] = useState('')
    const [loading, { toggle, close }] = useDisclosure(true);
    const [newRequestLink, setNewRequestLink] = useState('')
    const navigate = useNavigate()
    const [activePage, setActivePage] = useState(1);
    const [inactivePage, setInactivePage] = useState(1);

    // Sorting
    const [sortColumn, setSortColumn] = useState(null);
    const [sortDirection, setSortDirection] = useState("asc"); // 'asc' or 'desc'

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

    const transition = (id) => {
        navigate(`/detail/${id}`)
    }

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
                variant="light"
                size="xs"
                rightSection={<IconLogin2 />}
            >
                Open
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

    // Only enable sorting for these columns
    const sortableColumns = ["ID", "Submission Date", "Study Title"]

    // Mapping of column headers to object keys
    const columnKeyMap = {
        "ID": "intake_id",
        "Submission Date": "completion_timestamp",
        "Study Title": "research_title",
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
            item?.research_title ? item.research_title : "Not Provided",
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
            item?.research_title ? item.research_title : "Not Provided",
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
                <Title order={3}>Welcome to your intake dashboard</Title>
                <Text c="dimmed">Logged in as: {globalUsername}</Text>
                <Blockquote color="blue" iconSize={36} mt="lg" radius="md" icon={<IconInfoCircle/>}>
                    <List size="sm">
                        <List.Item>These tables represent active and completed research intakes affiliated with your username</List.Item>
                        <List.Item>You will see an entry in either table for any submissions that list your username as a contact</List.Item>
                        <List.Item>Missing a submission? Please contact your PI</List.Item>
                    </List>
                </Blockquote>
                <Divider my="md" />
                <Button
                    rightSection={<IconPlus size={20} />}
                    mb="md"
                    component="a"
                    href={newRequestLink}
                >New Request</Button>
                {error && <Blockquote mb="md" color="red"><strong>Error: </strong>{error}</Blockquote>}
                <Card id="active-card" withBorder shadow="sm" radius="md">
                    <Card.Section withBorder inheritPadding py="xs">
                        <div style={{display: 'flex', justifyContent: 'space-between', alignItems: 'center'}}>
                            <Text fw={500}>Active</Text>
                            <Tooltip label="List of intakes you have been added to">
                                <ActionIcon variant="subtle">
                                    <IconQuestionMark stroke={1.5}/>
                                </ActionIcon>
                            </Tooltip>
                            {loading && <Loader size={32} />}
                        </div>
                    </Card.Section>
                    <Card.Section className="table-section">
                        <Table mt="sm" className="main-table">
                            <Table.Thead>
                            <Table.Tr>
                                {tableData.head.map((col) => (
                                    <Table.Th
                                        key={col}
                                        onClick={() => sortableColumns.includes(col) && handleSort(col)} // Only trigger sorting for sortable columns
                                        className={sortableColumns.includes(col) ? 'sortable-column' : ''}
                                        style={{
                                            cursor: sortableColumns.includes(col) ? "pointer" : "default", // Set cursor to pointer for sortable columns
                                        }}
                                    >
                                        {col} {sortColumn === col ? (sortDirection === "asc" ? "↑" : "↓") : sortableColumns.includes(col) ? "⇅" : ""}
                                    </Table.Th>
                                ))}
                            </Table.Tr>
                            </Table.Thead>
                            <Table.Tbody>
                            {tableData.body.map((row, rowIndex) => (
                                <Table.Tr key={rowIndex}>
                                    {row.map((cell, cellIndex) => (
                                        <Table.Td key={cellIndex}>{cell}</Table.Td>
                                    ))}
                                </Table.Tr>
                            ))}
                            </Table.Tbody>
                        </Table>
                        <Pagination
                            total={pagesActive.length}
                            value={activePage}
                            onChange={setActivePage}
                            m="lg"
                        />
                    </Card.Section>
                </Card>

                <div style={{marginTop: '60px'}}></div>

                <Card withBorder shadow="sm" radius="md" id="inactive-table">
                    <Card.Section withBorder inheritPadding py="xs">
                        <div style={{display: 'flex', justifyContent: 'space-between', alignItems: 'center'}}>
                            <Text fw={500}>Inactive</Text>
                            <Tooltip label="List of deactivated intake projects - these entries have no dashboard functionality">
                                <ActionIcon variant="subtle">
                                    <IconQuestionMark stroke={1.5}/>
                                </ActionIcon>
                            </Tooltip>
                            {loading && <Loader size={32} />}
                        </div>
                    </Card.Section>
                    <Card.Section>
                        <Table
                            className="main-table"
                            data={finishedTable}
                        />
                        <Pagination
                            total={pagesInactive.length}
                            value={inactivePage}
                            onChange={setInactivePage}
                            m="lg"
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
