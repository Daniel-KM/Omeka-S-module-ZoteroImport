<?php
namespace ZoteroImport\Entity;

use Omeka\Entity\AbstractEntity;
use Omeka\Entity\Job;

/**
 * @Entity
 */
class ZoteroImport extends AbstractEntity
{
    /**
     * @Id
     * @Column(type="integer")
     * @GeneratedValue
     */
    protected $id;

    /**
     * @OneToOne(targetEntity="Omeka\Entity\Job")
     * @JoinColumn(onDelete="SET NULL")
     */
    protected $job;

    /**
     * @Column
     */
    protected $name;

    /**
     * @Column
     */
    protected $url;

    /**
     * @Column(type="integer")
     */
    protected $version;

    public function getId()
    {
        return $this->id;
    }

    public function setJob(Job $job)
    {
        $this->job = $job;
    }

    public function getJob()
    {
        return $this->job;
    }

    public function setName($name)
    {
        $this->name = $name;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setUrl($url)
    {
        $this->url = $url;
    }

    public function getUrl()
    {
        return $this->url;
    }

    public function setVersion($version)
    {
        $this->version = $version;
    }

    public function getVersion()
    {
        return $this->version;
    }
}