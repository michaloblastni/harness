package com.bci.harness.message.repository;

import com.bci.harness.message.entity.Message;
import com.bci.harness.victim.entity.Victim;
import org.springframework.data.jpa.repository.JpaRepository;

import java.util.List;
import java.util.Set;

public interface MessageRepository extends JpaRepository<Message, Long> {
    Message findByContent(String content);
}
