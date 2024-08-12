package com.bci.harness.message.service;

import com.bci.harness.message.entity.Message;
import com.bci.harness.message.repository.MessageRepository;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.stereotype.Service;

import java.util.List;

@Service
public class MessageServiceImpl implements MessageService {
    private MessageRepository messageRepository;

    @Autowired
    public MessageServiceImpl(MessageRepository messageRepository) {
        this.messageRepository = messageRepository;
    }

    @Override
    public long getInsultCount() {
        return messageRepository.count();
    }

    @Override
    public <S extends Message> List<S> saveAll(Iterable<S> entities) {
        return messageRepository.saveAll(entities);
    }

    @Override
    public Message findByContent(String content) {
        return messageRepository.findByContent(content);
    }

    @Override
    public <S extends Message> S save(S entity) {
        return messageRepository.save(entity);
    }
}
