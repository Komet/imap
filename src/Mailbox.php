<?php

namespace Ddeboer\Imap;

/**
 * An IMAP mailbox (commonly referred to as a ‘folder’)
 *
 */
class Mailbox implements \Countable, \IteratorAggregate
{
    private $mailbox;
    private $name;
    private $connection;

    /**
     * Constructor
     *
     * @param string     $name       Mailbox name
     * @param Connection $connection IMAP connection
     */
    public function __construct($name, Connection $connection)
    {
        $this->mailbox = $name;
        $this->connection = $connection;
        $this->name = substr($name, strpos($name, '}')+1);
    }

    /**
     * Get mailbox name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Get number of messages in this mailbox
     *
     * @return int
     */
    public function count()
    {
        $this->init();

        return imap_num_msg($this->connection->getResource());
    }

    /**
     * Get status of this mailbox
     *
     * @return object
     */
    public function status()
    {
        $this->init();

        return imap_status($this->connection->getResource(), $this->mailbox, \SA_ALL);
    }

    /**
     * Get message ids
     *
     * @param SearchExpression $search Search expression (optional)
	 * @param int              $offset Message offset
	 * @param int              $limit  Maximum number of messages
     *
     * @return MessageIterator|Message[]
     */
    public function getMessages(SearchExpression $search = null, $offset = null, $limit = null)
    {
        $this->init();

        $query = ($search ? (string) $search : 'ALL');

        if ($offset !== null || $limit !== null) {
            $messageNumbers = imap_sort($this->connection->getResource(), \SORTARRIVAL, 1, \SE_UID, $query);
            if ($messageNumbers !== false) {
                $messageNumbers = array_splice($messageNumbers, $offset !== null ? $offset : 0, $limit !== null ? $limit : 0);
            }
        } else {
            $messageNumbers = imap_search($this->connection->getResource(), $query, \SE_UID);
        }

        if (false == $messageNumbers) {
            // imap_search can also return false
            $messageNumbers = array();
        }

        return new MessageIterator($this->connection->getResource(), $messageNumbers);
    }

    /**
     * Get a message by message number
     *
     * @param int $number Message number
     *
     * @return Message
     */
    public function getMessage($number)
    {
        $this->init();

        return new Message($this->connection->getResource(), $number);
    }

    /**
     * Get messages in this mailbox
     *
     * @return MessageIterator
     */
    public function getIterator()
    {
        $this->init();

        return $this->getMessages();
    }

    /**
     * Delete this mailbox
     *
     */
    public function delete()
    {
        $this->connection->deleteMailbox($this);
    }

    /**
     * Delete all messages marked for deletion
     *
     * @return Mailbox
     */
    public function expunge()
    {
        $this->init();

        imap_expunge($this->connection->getResource());

        return $this;
    }

    /**
     * Add a message to the mailbox
     *
     * @param string $message
     * @param string $options
     *
     * @return boolean
     */
    public function addMessage($message, $options)
    {
        return imap_append($this->connection->getResource(), $this->mailbox, $message, $options);
    }

    /**
     * If connection is not currently in this mailbox, switch it to this mailbox
     */
    private function init()
    {
        $check = imap_check($this->connection->getResource());
        if ($check === false || $check->Mailbox != $this->mailbox) {
            imap_reopen($this->connection->getResource(), $this->mailbox);
        }
    }
}
