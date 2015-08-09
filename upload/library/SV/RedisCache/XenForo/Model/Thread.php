<?php

class SV_RedisCache_XenForo_Model_Thread extends XFCP_SV_RedisCache_XenForo_Model_Thread
{
    public function countThreadsInForum($forumId, array $conditions = array())
    {
        $options = XenForo_Application::getOptions();
        if ($options->sv_threadcount_caching && $cache = $this->_getCache(true))
        {
            static $cachePrefix = null;
            if ($cachePrefix === null)
            {
                $cachePrefix = XenForo_Application::get('options')->cachePrefix;
            }
            $key = $cachePrefix . 'forum_' . $forumId . '_threadcount_' . md5(serialize($conditions));

            $cacheData = $cache->load($key);
            if ($cacheData !== false)
            {
                return unserialize($cacheData);
            }

            $data = parent::countThreadsInForum($forumId, $conditions);

            if ($data !== false)
            {
                $cache->save(serialize($data), $key, array(), $data <= $options->sv_threadcount_short * $options->discussionsPerPage ? $options->sv_threadcountcache_short : $options->sv_threadcountcache_long);
            }
            else
            {
                $cache->remove($key);
            }
            return $data;
        }

        return parent::countThreadsInForum($forumId, $conditions);
    }

    public function getThreadsInForum($forumId, array $conditions = array(), array $fetchOptions = array())
    {
        $limitOptions = $this->prepareLimitFetchOptions($fetchOptions);
        $options = XenForo_Application::getOptions();
        if ($limitOptions['offset'] <= $options->sv_forumquery_threshold)
        {
            return parent::getThreadsInForum($forumId, $conditions, $fetchOptions);
        }

        $conditions['forum_id'] = $forumId;
        $whereConditions = $this->prepareThreadConditions($conditions, $fetchOptions);
        $sqlClauses = $this->prepareThreadFetchOptions($fetchOptions);
        $forceIndex = (!empty($fetchOptions['forceThreadIndex']) ? 'FORCE INDEX (' . $fetchOptions['forceThreadIndex'] . ')' : '');

        return $this->fetchAllKeyed('
                SELECT thread.*
                    ' . $sqlClauses['selectFields'] . '
                FROM (
                '. $this->limitQueryResults('
                    SELECT thread.thread_id
                    FROM xf_thread AS thread ' . $forceIndex . '
                    ' . $sqlClauses['joinTables'] . '
                    WHERE ' . $whereConditions . '
                    ' . $sqlClauses['orderClause'] . '
                ', $limitOptions['limit'], $limitOptions['offset']
                ) . ') threadId
                JOIN xf_thread AS thread on thread.thread_id = threadId.thread_id '
            . $sqlClauses['joinTables']
            , 'thread_id');
    }

}